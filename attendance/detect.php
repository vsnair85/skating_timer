<?php require '../script/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Detect Face</title>
<meta name="viewport" content="width=device-width, initial-scale=1" />
<style>
  body { font-family: system-ui, Arial, sans-serif; max-width: 960px; margin: 24px auto; }
  video, canvas { width: 360px; height: 270px; background:#000; border-radius:8px; }
  .status { margin-top:8px; }
  a.btn, button { display:inline-block; margin-top:18px; padding:10px 14px; border:0; background:#111; color:#fff; border-radius:8px; text-decoration:none; cursor:pointer; }
</style>
</head>
<body>
  <h3>Detecting…</h3>
  <video id="video" autoplay muted playsinline></video>
  <canvas id="overlay" width="360" height="270" style="display:none"></canvas>
  <div id="status" class="status">Loading models…</div>
  <p>
    <a class="btn" href="index.php">Cancel</a>
    <button id="switchBtn">Switch Camera</button>
  </p>

  <script src="face-api.min.js"></script>
  <script>
  const video = document.getElementById('video');
  const overlay = document.getElementById('overlay');
  const ctx = overlay.getContext('2d');
  const statusEl = document.getElementById('status');
  const switchBtn = document.getElementById('switchBtn');

  let lastDescriptor = null;
  let recognized = false;
  let currentFacingMode = 'environment'; // ✅ default to back camera
  let stream = null;

  function modelsPath() {
    const base = new URL('.', location.href);
    return new URL('faceapi', base).pathname; // './faceapi'
  }

  async function loadModels() {
    const p = modelsPath();
    await faceapi.nets.tinyFaceDetector.loadFromUri(p);
    await faceapi.nets.faceLandmark68Net.loadFromUri(p);
    await faceapi.nets.faceRecognitionNet.loadFromUri(p);
  }

  async function initCamera() {
    if (stream) {
      stream.getTracks().forEach(track => track.stop()); // stop old stream
    }
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { exact: currentFacingMode } },
        audio: false
      });
    } catch (e) {
      // fallback if exact not supported
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: currentFacingMode },
        audio: false
      });
    }
    video.srcObject = stream;
    video.onloadedmetadata = () => {
      video.play();
      statusEl.textContent = 'Center your face…';
      loop();
    };
  }

  async function start() {
    try {
      await loadModels();
      statusEl.textContent = 'Models loaded. Initializing camera…';
      await initCamera();
    } catch (e) {
      statusEl.textContent = 'Init failed: ' + e.message;
    }
  }

  function snapshotBase64() {
    overlay.width = video.videoWidth || 360;
    overlay.height = video.videoHeight || 270;
    ctx.drawImage(video, 0, 0, overlay.width, overlay.height);
    return overlay.toDataURL('image/jpeg', 0.85);
  }

  async function loop() {
    if (!video.videoWidth) return requestAnimationFrame(loop);

    const det = await faceapi
      .detectSingleFace(video, new faceapi.TinyFaceDetectorOptions({ inputSize: 256, scoreThreshold: 0.5 }))
      .withFaceLandmarks()
      .withFaceDescriptor();

    if (det && !recognized) {
      lastDescriptor = Array.from(det.descriptor);
      statusEl.textContent = 'Face detected. Checking…';

      try {
        const res = await fetch('recognize.php', {
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ embedding: lastDescriptor })
        });
        const data = await res.json();

        sessionStorage.setItem('lastEmbedding', JSON.stringify(lastDescriptor));
        sessionStorage.setItem('lastSnapshot', snapshotBase64());

        if (data.ok && data.match) {
          recognized = true;
          location.href = 'confirm.php?id=' + encodeURIComponent(data.racer.id);
          return;
        } else if (data.ok && !data.match) {
          recognized = true;
          location.href = 'enroll_form.php';
          return;
        } else {
          statusEl.textContent = 'Server error: ' + (data.error || 'unknown');
        }
      } catch (e) {
        statusEl.textContent = 'Error: ' + e.message;
      }
    } else if (!det) {
      statusEl.textContent = 'No face found. Please align your face.';
    }

    requestAnimationFrame(loop);
  }

  // ✅ switch camera button
  switchBtn.addEventListener('click', async () => {
    currentFacingMode = (currentFacingMode === 'user') ? 'environment' : 'user';
    recognized = false;
    await initCamera();
  });

  start();
  </script>
</body>
</html>
