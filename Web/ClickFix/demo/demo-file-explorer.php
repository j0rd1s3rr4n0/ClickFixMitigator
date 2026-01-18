<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ClickFix Demo - File Explorer Paste</title>
    <style>
      body {
        font-family: system-ui, sans-serif;
        background: #f8fafc;
        color: #0f172a;
        margin: 0;
        padding: 40px;
      }
      .panel {
        background: #ffffff;
        border-radius: 18px;
        padding: 24px;
        max-width: 680px;
        margin: 0 auto;
        box-shadow: 0 16px 32px rgba(15, 23, 42, 0.15);
      }
      .panel h1 {
        margin-top: 0;
      }
      .path {
        border: 2px solid #3b82f6;
        padding: 12px;
        border-radius: 10px;
        font-family: "Segoe UI", system-ui, sans-serif;
        background: #eff6ff;
        margin: 16px 0;
      }
      .steps {
        margin-top: 16px;
      }
      .steps li {
        margin-bottom: 8px;
      }
      kbd {
        background: #0f172a;
        color: #fff;
        border-radius: 6px;
        padding: 2px 6px;
        font-size: 12px;
      }
      .cta {
        margin-top: 16px;
        display: inline-block;
        background: #2563eb;
        color: #fff;
        padding: 10px 14px;
        border-radius: 10px;
        text-decoration: none;
        font-weight: 600;
      }
    </style>
  </head>
  <body>
    <section class="panel">
      <h1>Meta Help Support</h1>
      <p>Your account review is ready. Follow these steps to open the report:</p>
      <div class="path">C:\Users\Default\Documents\Meta\Facebook\Shared\Incident_reported.pdf</div>
      <ol class="steps">
        <li>Open File Explorer.</li>
        <li>Select the address bar (<kbd>Ctrl</kbd> + <kbd>L</kbd> or <kbd>Alt</kbd> + <kbd>D</kbd>).</li>
        <li>Paste the file path with <kbd>Ctrl</kbd> + <kbd>V</kbd> and press <kbd>Enter</kbd>.</li>
      </ol>
      <a class="cta" href="#" role="button">Open File Explorer</a>
    </section>
  </body>
</html>
