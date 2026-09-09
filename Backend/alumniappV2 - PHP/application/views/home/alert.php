
<?php defined('BASEPATH') OR exit('No direct script access allowed');
echo'
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Success</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

<style>
    body {
        margin: 0;
        padding: 0;
        background: #eef1f5;
        font-family:"Poppins", sans-serif;
        height: 100vh;
        overflow: hidden;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    /* Mobile Modal Container */
    .modal-container {
        position: relative;
        width: 92%;
        max-width: 360px;
        text-align: center;
        animation: fadeZoom 0.45s ease-out;
        z-index: 10;
    }

    @keyframes fadeZoom {
        0% { transform: scale(0.4); opacity: 0; }
        100% { transform: scale(1); opacity: 1; }
    }

    /* Card Style */
    .card {
        background: #fff;
        padding: 30px 22px 25px;
        border-radius: 22px;
        box-shadow: 0 10px 35px rgba(0,0,0,0.12);
        position: relative;
    }

    /* Title & Message */
    .title {
        font-size: 22px;
        font-weight: 600;
        margin-top: -10px;
        margin-bottom: 6px;
    }

    .message {
        color: #666;
        font-size: 15px;
        margin-bottom: 20px;
    }

    /* Button */
    .btn {
        display: inline-block;
        background: #007bff;
        padding: 12px 22px;
        color: #fff;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 15px;
    }

    .btn:active {
        transform: scale(0.96);
    }

    /* Sparkles on TOP */
    .sparkle {
        position: absolute;
        width: 10px;
        height: 10px;
        background: #ffd700;
        border-radius: 50%;
        pointer-events: none;
        z-index: 999;
        animation: sparkleBlink 1.5s infinite ease-in-out;
        opacity: 0;
    }

    @keyframes sparkleBlink {
        0% { transform: scale(0.6); opacity: 0; }
        50% { transform: scale(1.2); opacity: 1; }
        100% { transform: scale(0.6); opacity: 0; }
    }

    /* Falling Confetti */
    .confetti-piece {
        position: fixed;
        width: 10px;
        height: 10px;
        top: -10px;
        opacity: 0.9;
        border-radius: 2px;
        z-index: 9999;
        animation: fall linear forwards;
    }

    @keyframes fall {
        to {
            transform: translateY(110vh) rotate(360deg);
            opacity: 0;
        }
    }
</style>
</head>

<body>

<!-- Modal -->
<div class="modal-container">

    <div class="card">

        <!-- Lottie Animation -->
        <lottie-player 
            src="https://assets10.lottiefiles.com/packages/lf20_jbrw3hcz.json"
            background="transparent"  
            speed="1"  
            style="width: 140px; height: 140px; margin: 0 auto;"
            autoplay>
        </lottie-player>

        <div class="title">Password Updated</div>

        <div class="message">
            Your password has been updated successfully.<br>
            You can now go to the app and log in with your new password.
        </div>

       
    </div>

</div>


<!-- Lottie Script -->
<script src="https://unpkg.com/@lottiefiles/lottie-player@latest/dist/lottie-player.js"></script>

<!-- Sparkles On Card -->
<script>
function createSparkle() {
    let s = document.createElement("div");
    s.classList.add("sparkle");

    const modal = document.querySelector(".modal-container");
    const rect = modal.getBoundingClientRect();

    s.style.left = (rect.left + Math.random() * rect.width) + "px";
    s.style.top = (rect.top + Math.random() * rect.height) + "px";
    s.style.animationDuration = (1 + Math.random() * 1.2) + "s";
    s.style.background = ["#ffd700", "#fff", "#fff7c2"][Math.floor(Math.random()*3)];

    document.body.appendChild(s);

    setTimeout(() => s.remove(), 2000);
}

setInterval(createSparkle, 180);
</script>

<!-- Confetti Falling On Top -->
<script>
function createConfetti() {
    const colors = ["#ff4f81", "#ffcd3c", "#4fc3f7", "#4caf50", "#ff9800"];
    const c = document.createElement("div");
    c.classList.add("confetti-piece");

    c.style.left = Math.random() * window.innerWidth + "px";
    c.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
    c.style.animationDuration = (1.8 + Math.random() * 2) + "s";

    document.body.appendChild(c);

    setTimeout(() => c.remove(), 3000);
}

setInterval(createConfetti, 120);
</script>

</body>
</html>

';