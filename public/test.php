<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PHP Setup Test</title>
  <style>
    body {
      font-family: system-ui, -apple-system, sans-serif;
      background-color: #f8f9fa;
      color: #2b2d42;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }

    .card {
      background: white;
      padding: 2rem;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
      text-align: center;
      max-width: 420px;
    }

    .badge {
      display: inline-block;
      background-color: #2a9d8f;
      color: white;
      padding: 0.35rem 0.8rem;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: bold;
      margin-bottom: 1rem;
    }

    .info {
      text-align: left;
      background: #e9ecef;
      padding: 1rem;
      border-radius: 8px;
      font-size: 0.9rem;
      margin-top: 1rem;
    }
  </style>
</head>

<body>
  <div class="card">
    <span class="badge">PHP Active</span>
    <h2>PHP is Working!</h2>
    <p>Your local server successfully parsed this PHP script.</p>
    <div class="info">
      <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
      <p><strong>Server Time:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
      <p><strong>Loaded Drivers:</strong> <?php echo implode(', ', PDO::getAvailableDrivers()); ?></p>
    </div>
  </div>
</body>

</html>