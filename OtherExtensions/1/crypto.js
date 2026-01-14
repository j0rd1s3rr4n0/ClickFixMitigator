/**
 * BinHex.Ninja Security - Browser Crypto Module
 *
 * Implements ECDH key exchange and AES-256-GCM encryption using Web Crypto API
 *
 * Security:
 * - ECDH with P-384 curve (matches server)
 * - AES-256-GCM for authenticated encryption
 * - Ephemeral keys generated on install
 * - Keys stored in chrome.storage.local (encrypted by browser)
 */

class BinHexCrypto {
    constructor() {
        this.keyPair = null;
        this.serverPublicKey = null;
        this.sharedSecret = null;
        this.clientKeyHash = null;
    }

    /**
     * Generate ECDH key pair (P-384 curve)
     */
    async generateKeyPair() {
        try {
            this.keyPair = await self.crypto.subtle.generateKey(
                {
                    name: "ECDH",
                    namedCurve: "P-384" // secp384r1
                },
                true, // extractable
                ["deriveKey", "deriveBits"]
            );

            console.log("[Crypto] Generated ECDH key pair");
            return true;
        } catch (error) {
            console.error("[Crypto] Failed to generate key pair:", error);
            return false;
        }
    }

    /**
     * Export public key to PEM format
     */
    async exportPublicKeyPEM() {
        try {
            const exported = await self.crypto.subtle.exportKey("spki", this.keyPair.publicKey);
            const exportedAsBase64 = this.arrayBufferToBase64(exported);
            const pem = `-----BEGIN PUBLIC KEY-----\n${this.formatPEM(exportedAsBase64)}\n-----END PUBLIC KEY-----`;
            return pem;
        } catch (error) {
            console.error("[Crypto] Failed to export public key:", error);
            return null;
        }
    }

    /**
     * Import server's public key from PEM
     */
    async importServerPublicKey(pem) {
        try {
            // Remove PEM headers and format
            const pemContents = pem
                .replace(/-----BEGIN PUBLIC KEY-----/, "")
                .replace(/-----END PUBLIC KEY-----/, "")
                .replace(/\s/g, "");

            const binaryDer = this.base64ToArrayBuffer(pemContents);

            this.serverPublicKey = await self.crypto.subtle.importKey(
                "spki",
                binaryDer,
                {
                    name: "ECDH",
                    namedCurve: "P-384"
                },
                false,
                []
            );

            console.log("[Crypto] Imported server public key");
            return true;
        } catch (error) {
            console.error("[Crypto] Failed to import server public key:", error);
            return false;
        }
    }

    /**
     * Derive shared secret using ECDH
     */
    async deriveSharedSecret() {
        try {
            // Derive bits from ECDH
            const sharedBits = await self.crypto.subtle.deriveBits(
                {
                    name: "ECDH",
                    public: this.serverPublicKey
                },
                this.keyPair.privateKey,
                384 // P-384 curve produces 384 bits
            );

            // Derive AES key using HKDF
            const importedKey = await self.crypto.subtle.importKey(
                "raw",
                sharedBits,
                "HKDF",
                false,
                ["deriveKey"]
            );

            this.sharedSecret = await self.crypto.subtle.deriveKey(
                {
                    name: "HKDF",
                    hash: "SHA-256",
                    salt: new Uint8Array(),
                    info: new TextEncoder().encode("BinHex.Ninja-AES-Key")
                },
                importedKey,
                {
                    name: "AES-GCM",
                    length: 256
                },
                false,
                ["encrypt", "decrypt"]
            );

            console.log("[Crypto] Derived shared secret");
            return true;
        } catch (error) {
            console.error("[Crypto] Failed to derive shared secret:", error);
            return false;
        }
    }

    /**
     * Encrypt data using AES-256-GCM
     */
    async encrypt(plaintext) {
        try {
            const iv = self.crypto.getRandomValues(new Uint8Array(12)); // 96-bit IV for GCM
            const encodedText = new TextEncoder().encode(plaintext);
            const aad = new TextEncoder().encode("BinHex.Ninja.Security");

            const ciphertext = await self.crypto.subtle.encrypt(
                {
                    name: "AES-GCM",
                    iv: iv,
                    additionalData: aad,
                    tagLength: 128
                },
                this.sharedSecret,
                encodedText
            );

            // Split ciphertext and tag
            const ciphertextArray = new Uint8Array(ciphertext);
            const actualCiphertext = ciphertextArray.slice(0, -16);
            const tag = ciphertextArray.slice(-16);

            // Package everything together
            const payload = {
                ciphertext: this.arrayBufferToBase64(actualCiphertext),
                iv: this.arrayBufferToBase64(iv),
                tag: this.arrayBufferToBase64(tag),
                aad: "BinHex.Ninja.Security"
            };

            return JSON.stringify(payload);
        } catch (error) {
            console.error("[Crypto] Encryption failed:", error);
            return null;
        }
    }

    /**
     * Decrypt data using AES-256-GCM
     */
    async decrypt(encryptedPackage) {
        try {
            const payload = JSON.parse(encryptedPackage);

            const ciphertext = this.base64ToArrayBuffer(payload.ciphertext);
            const iv = this.base64ToArrayBuffer(payload.iv);
            const tag = this.base64ToArrayBuffer(payload.tag);

            // Combine ciphertext and tag for Web Crypto API
            const combined = new Uint8Array(ciphertext.byteLength + tag.byteLength);
            combined.set(new Uint8Array(ciphertext), 0);
            combined.set(new Uint8Array(tag), ciphertext.byteLength);

            const decrypted = await self.crypto.subtle.decrypt(
                {
                    name: "AES-GCM",
                    iv: iv,
                    additionalData: new TextEncoder().encode(payload.aad),
                    tagLength: 128
                },
                this.sharedSecret,
                combined
            );

            return new TextDecoder().decode(decrypted);
        } catch (error) {
            console.error("[Crypto] Decryption failed:", error);
            return null;
        }
    }

    /**
     * Perform key exchange with server
     */
    async performKeyExchange(apiKey, serverUrl) {
        try {
            // Generate our key pair
            await this.generateKeyPair();

            // Export our public key
            const clientPublicKeyPEM = await this.exportPublicKeyPEM();
            if (!clientPublicKeyPEM) {
                throw new Error("Failed to export public key");
            }

            // Calculate client key hash for session identification
            const encoder = new TextEncoder();
            const keyData = encoder.encode(clientPublicKeyPEM);
            const hashBuffer = await self.crypto.subtle.digest('SHA-256', keyData);
            this.clientKeyHash = Array.from(new Uint8Array(hashBuffer))
                .map(b => b.toString(16).padStart(2, '0'))
                .join('');

            // Send to server
            console.log("[Crypto] Sending key exchange request to:", `${serverUrl}/key-exchange`);
            console.log("[Crypto] API Key (first 8 chars):", apiKey.substring(0, 8) + "...");
            console.log("[Crypto] Client Public Key (first 100 chars):", clientPublicKeyPEM.substring(0, 100) + "...");
            
            const response = await fetch(`${serverUrl}/key-exchange`, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-API-Key": apiKey
                },
                body: JSON.stringify({
                    clientPublicKey: clientPublicKeyPEM
                })
            });

            console.log("[Crypto] Key exchange response status:", response.status);
            
            if (!response.ok) {
                let errorData;
                let errorText = '';
                try {
                    errorData = await response.json();
                    errorText = errorData.error || JSON.stringify(errorData);
                } catch (e) {
                    errorText = await response.text();
                    errorData = { error: errorText };
                }
                console.error("[Crypto] Key exchange failed with response:");
                console.error("  Status:", response.status);
                console.error("  Error Data:", JSON.stringify(errorData, null, 2));
                console.error("  Error Text:", errorText);
                throw new Error(`Key exchange failed (${response.status}): ${errorText || response.statusText}`);
            }

            const responseData = await response.json();

            // Import server's public key
            await this.importServerPublicKey(responseData.serverPublicKey);

            // Derive shared secret
            await this.deriveSharedSecret();

            console.log("[Crypto] Key exchange successful");
            console.log("[Crypto] Client Key Hash:", this.clientKeyHash);

            return {
                success: true,
                clientKeyHash: this.clientKeyHash,
                expiresAt: responseData.expiresAt
            };
        } catch (error) {
            console.error("[Crypto] Key exchange error:", error);
            return {
                success: false,
                error: error.message
            };
        }
    }

    /**
     * Helper: Convert ArrayBuffer to Base64
     */
    arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        for (let i = 0; i < bytes.byteLength; i++) {
            binary += String.fromCharCode(bytes[i]);
        }
        return btoa(binary);
    }

    /**
     * Helper: Convert Base64 to ArrayBuffer
     */
    base64ToArrayBuffer(base64) {
        const binary = atob(base64);
        const bytes = new Uint8Array(binary.length);
        for (let i = 0; i < binary.length; i++) {
            bytes[i] = binary.charCodeAt(i);
        }
        return bytes.buffer;
    }

    /**
     * Helper: Format base64 string for PEM (64 chars per line)
     */
    formatPEM(base64) {
        const formatted = [];
        for (let i = 0; i < base64.length; i += 64) {
            formatted.push(base64.substring(i, i + 64));
        }
        return formatted.join('\n');
    }
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = BinHexCrypto;
}
