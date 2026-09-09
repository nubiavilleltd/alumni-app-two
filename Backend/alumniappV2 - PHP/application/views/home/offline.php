<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Offline | Estate Management</title>
  <link rel="stylesheet" href="/assets/css/theme.css">
  <style>
    body {
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      height: 100vh;
      background-color: #f8f9fa;
      text-align: center;
      font-family: "Inter", sans-serif;
    }
    .logo {
      width: 80px;
      height: 80px;
      margin-bottom: 20px;
    }
    h1 {
      font-size: 1.8rem;
      font-weight: 600;
      color: #222;
    }
    p {
      color: #555;
      margin: 10px 0 25px;
    }
    button {
      background-color: #007bff;
      border: none;
      color: white;
      padding: 10px 24px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 15px;
    }
    button:hover {
      background-color: #0056b3;
    }
    .reconnecting {
      display: none;
      color: #007bff;
      margin-top: 15px;
      font-weight: 500;
      font-size: 14px;
      animation: blink 1.5s infinite;
    }
    @keyframes blink {
      50% { opacity: 0.4; }
    }
  </style>
</head>
<body>

  <img src="/assets/img/icons/icon-144.png" alt="Logo" class="logo">
  <h1>You’re Offline</h1>
  <p>Please check your internet connection and try again.</p>
  <button id="retry-btn">Retry</button>

  <div class="reconnecting" id="reconnecting-msg">🔄 Reconnecting...</div>

  <script>
  document.addEventListener("DOMContentLoaded", () => {
    const retryBtn = document.getElementById("retry-btn");
    const reconnectingMsg = document.getElementById("reconnecting-msg");

    // Get previously visited URL
    const prevUrl = localStorage.getItem("last-online-url") || "/";

    // Manual Retry
    retryBtn.addEventListener("click", () => {
      reconnectingMsg.style.display = "block";
      retryBtn.disabled = true;
      retryBtn.innerText = "Reconnecting...";
      setTimeout(() => window.location.href = prevUrl, 1500);
    });

    // Auto restore when network returns
    window.addEventListener("online", () => {
      reconnectingMsg.style.display = "block";
      reconnectingMsg.innerText = "✅ Connection restored! Returning...";
      setTimeout(() => window.location.href = prevUrl, 1000);
    });
  });
  </script>
</body>
</html>
