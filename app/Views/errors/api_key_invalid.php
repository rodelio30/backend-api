<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Widget - Invalid API Key</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 500px;
            width: 100%;
        }
        .error-icon {
            font-size: 64px;
            color: #dc3545;
            margin-bottom: 20px;
        }
        .error-title {
            color: #333;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
        }
        .error-message {
            color: #666;
            font-size: 16px;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .error-details {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            font-size: 14px;
            color: #6c757d;
            border-left: 4px solid #dc3545;
        }
        .contact-info {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🚫</div>
        <h1 class="error-title">Access Denied</h1>
        <p class="error-message">
            The chat widget could not be loaded due to an invalid or inactive API key.
        </p>
        <div class="error-details">
            <strong>Error:</strong> <?= esc($error ?? 'Invalid API key') ?>
        </div>
        <div class="contact-info">
            <p>If you are the website owner, please:</p>
            <ul style="text-align: left; display: inline-block;">
                <li>Check that your API key is correct</li>
                <li>Ensure your API key is active</li>
                <li>Verify your domain is authorized</li>
                <li>Contact support if the issue persists</li>
            </ul>
        </div>
    </div>
</body>
</html>
