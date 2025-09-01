<?php
require 'script/db.php';
require 'script/auth.php';
$racer_id = isset($_GET['racer_id']) ? (int)$_GET['racer_id'] : 0;

$stmt = $mysqli->prepare("SELECT tr_id, tr_name, tr_number FROM tbl_racers WHERE tr_id=? LIMIT 1");
$stmt->bind_param('i', $racer_id);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$r) { header('Location: /index.php'); exit; }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Stopwatch</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    :root{ --sky:#52b6ff; --sky-2:#e8f5ff; }
    body{ background:#fff; min-height:100svh; display:flex; flex-direction:column; font-family:system-ui,-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;}
    .topbar{ background:var(--sky); color:#fff; padding:14px 16px; display:flex; align-items:center; justify-content:space-between;}
    .racer-pill{ background:rgba(255,255,255,.25); padding:6px 10px; border-radius:999px; font-weight:600;}
    .wrap{ flex:1; display:flex; align-items:center; justify-content:center; padding:16px;}
    .timer-card{ width:100%; max-width:520px; border-radius:18px; border:none; box-shadow:0 10px 30px rgba(0,0,0,.06); overflow:hidden;}
    .time{ font-variant-numeric:tabular-nums; letter-spacing:.5px; font-size:clamp(40px,11vw,64px); font-weight:800; text-align:center; color:#0d1b2a;}
    .ms{ font-size:.6em; opacity:.8;}
    .controls .btn{ border-radius:12px; padding:12px 16px; font-weight:600;}
    .btn-primary{ background:var(--sky); border-color:var(--sky);}
    .list-group-item{ border:0; border-bottom:1px solid #eee;}
    .list-group-item small{ color:#6b7280;}
    .empty{ text-align:center; color:#9aa4b2; padding:10px 0;}
  </style>
</head>
<body>
  <div class="topbar">
    <div><a href="index.php" class="link-light text-decoration-none">&larr; Back</a></div>
    <div class="racer-pill">
      <?= htmlspecialchars($r['tr_name']) ?><?= $r['tr_number'] ? ' — #'.htmlspecialchars($r['tr_number']) : '' ?>
    </div>
    <div style="width:42px"></div>
  </div>

  <div class="wrap">
    <div class="card timer-card">
      <div class="card-body p-3 p-sm-4">
        <div class="time mb-3">
          <span id="mm">00</span>:<span id="ss">00</span>.<span id="ms" class="ms">000</span>
        </div>

        <div class="controls d-grid gap-2" style="grid-template-columns: repeat(4, 1fr);">
          <button id="btnStart" class="btn btn-primary">Start</button>
          <button id="btnLap" class="btn btn-outline-primary" disabled>Lap</button>
          <button id="btnStop" class="btn btn-outline-danger" disabled>Stop</button>
          <button id="btnSave" class="btn btn-success" disabled>Save</button>
        </div>

        <div class="mt-3">
          <ul id="laps" class="list-group small"></ul>
          <div id="emptyLaps" class="empty">No laps yet.</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // Elements
    const elMM = document.getElementById('mm');
    const elSS = document.getElementById('ss');
    const elMS = document.getElementById('ms');
    const lapsEl = document.getElementById('laps');
    const emptyLaps = document.getElementById('emptyLaps');

    const btnStart = document.getElementById('btnStart');
    const btnLap   = document.getElementById('btnLap');
    const btnStop  = document.getElementById('btnStop');
    const btnSave  = document.getElementById('btnSave');

    // State
    let running=false, rafId=null, startPerf=0, pausedElapsed=0, lastLapElapsed=0, totalElapsed=0;
    let laps=[]; let wallStart=null; let wallEnd=null;

    // Helpers
    function format(ms){
      const mm=Math.floor(ms/60000);
      const ss=Math.floor((ms%60000)/1000);
      const ms3=Math.floor(ms%1000);
      return {mm:String(mm).padStart(2,'0'), ss:String(ss).padStart(2,'0'), ms:String(ms3).padStart(3,'0')};
    }
    function render(ms){ const f=format(ms); elMM.textContent=f.mm; elSS.textContent=f.ss; elMS.textContent=f.ms; }
    function tick(){ const now=performance.now(); totalElapsed=(now-startPerf)+pausedElapsed; render(totalElapsed); rafId=requestAnimationFrame(tick); }
    function setButtons(){ btnStart.disabled=running; btnLap.disabled=!running; btnStop.disabled=!running; btnSave.disabled=running||totalElapsed<=0; }

    function drawLaps(){
      lapsEl.innerHTML='';
      if(!laps.length){ emptyLaps.style.display=''; return; }
      emptyLaps.style.display='none';
      laps.forEach(l=>{
        const li=document.createElement('li');
        li.className='list-group-item d-flex justify-content-between align-items-center';
        const lf=format(l.lapMs), cf=format(l.cumulative);
        li.innerHTML = `
          <div><strong>Lap ${l.no}</strong></div>
          <div class="text-end">
            <div>${lf.mm}:${lf.ss}.${lf.ms}</div>
            <small>Cumulative: ${cf.mm}:${cf.ss}.${cf.ms}</small>
          </div>`;
        lapsEl.appendChild(li);
      });
    }

    // NEW: helper to push a lap; if isFinal=true we allow zero-length if needed
    function pushLap(isFinal=false){
      const lapMs = totalElapsed - lastLapElapsed;
      if (lapMs <= 0 && !isFinal) return; // ignore zero-length during normal laps
      lastLapElapsed = totalElapsed;
      laps.push({
        no: laps.length + 1,
        lapMs: Math.max(0, Math.floor(lapMs)),
        cumulative: Math.max(0, Math.floor(totalElapsed))
      });
      drawLaps();
    }

    // Events
    btnStart.addEventListener('click', ()=>{
      if(running) return;
      running = true;
      wallStart = wallStart || new Date();
      startPerf = performance.now();
      rafId = requestAnimationFrame(tick);
      setButtons();
    });

    btnLap.addEventListener('click', ()=>{
      if(!running) return;
      pushLap(false); // regular lap
    });

    btnStop.addEventListener('click', ()=>{
      if(!running) return;

      // Automatically create the final lap from lastLapElapsed -> stop time
      if (totalElapsed > lastLapElapsed) {
        pushLap(true); // final lap
      }

      running = false;
      cancelAnimationFrame(rafId);
      rafId = null;
      pausedElapsed = totalElapsed;
      wallEnd = new Date();
      setButtons();
    });

    btnSave.addEventListener('click', async ()=>{
      if(running || totalElapsed <= 0) return;

      const payload={
        racer_id: <?= (int)$r['tr_id'] ?>,
        total_ms: Math.floor(totalElapsed),
        started_at_iso: wallStart?wallStart.toISOString():null,
        finished_at_iso: wallEnd?wallEnd.toISOString():new Date().toISOString(),
        laps: laps
      };

      btnSave.disabled=true;
      try{
        const res=await fetch('script/save_race.php',{
          method:'POST',
          headers:{'Content-Type':'application/json'},
          body:JSON.stringify(payload)
        });
        const data=await res.json();
        if(data.ok){
          alert('Saved! Race ID: '+data.race_id);
          // reset for next run
          cancelAnimationFrame(rafId);
          running=false; rafId=null;
          startPerf=0; pausedElapsed=0; lastLapElapsed=0; totalElapsed=0;
          laps=[]; wallStart=null; wallEnd=null;
          render(0); drawLaps(); setButtons();
        }else{
          alert('Save failed.');
          btnSave.disabled=false;
        }
      }catch(e){
        alert('Network/Server error.');
        btnSave.disabled=false;
      }
    });

    // Init
    render(0); drawLaps(); setButtons();
  </script>
</body>
</html>
