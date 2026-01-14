// BinHex.Ninja Security - Production Configuration

const CONFIG = {
  // Production API endpoint
  SERVER_URL: "https://api.binhex.ninja",
  
  // Production API key
  API_KEY: "d6ffe0d622930de41a3cff7ed4d6111084383d30d2121fb773b9fb122d3f22e7",
  
  // Trusted domains that bypass detection
  TRUSTED_DOMAINS: [
    "github.com",
    "stackoverflow.com",
    "developer.mozilla.org",
    "binhex.ninja",
  ],

  // Default settings
  DEFAULTS: {
    dataCollection: "none", // Default to disabled until user chooses
    loggingEndpoint: "https://api.binhex.ninja/log",
    userWhitelist: [],
  },

  // App version
  VERSION: "1.0.0",
};

console.log("[Config] Loaded production configuration - SERVER:", CONFIG.SERVER_URL);

