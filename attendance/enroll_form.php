<?php require '../script/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Enroll New Racer</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        body {
            font-family: system-ui, Arial, sans-serif;
            max-width: 640px;
            margin: 32px auto;
        }

        label {
            display: block;
            margin: 10px 0 4px;
        }

        input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }

        .preview {
            margin-top: 12px;
        }

        button {
            margin-top: 16px;
            padding: 10px 14px;
            border: 0;
            background: #111;
            color: #fff;
            border-radius: 8px;
            cursor: pointer;
        }

        a {
            margin-left: 8px;
        }
    </style>
</head>

<body>
    <h3>Enroll New Racer</h3>
    <form id="f" method="post" action="enroll.php">
        <label>Name</label>
        <input name="name" id="name" required />
        <label>Mobile Number</label>
        <input name="number" id="number" type="number" placeholder="10 Digit Mobile number" required maxlength="10" oninput="this.value=this.value.replace(/[^0-9]/g,'');" />
        <input type="hidden" name="embedding" id="embedding" />
        <input type="hidden" name="image_base64" id="image_base64" />
        <div class="preview">
            <img id="preview" alt="Snapshot" style="max-width:240px; border-radius:8px; display:none" />
        </div>
        <button type="submit">Save</button>
        <a href="index.php">Cancel</a>
    </form>

    <script>
        // Load data saved by detect.php
        const emb = sessionStorage.getItem('lastEmbedding');
        const snap = sessionStorage.getItem('lastSnapshot');

        if (!emb) alert("Missing face data. Please run detection again.");

        document.getElementById('embedding').value = emb || '[]';
        if (snap) {
            document.getElementById('image_base64').value = snap;
            const img = document.getElementById('preview');
            img.src = snap;
            img.style.display = 'block';
        }
    </script>
    <script>
        document.getElementById("number").addEventListener("blur", function() {
            if (this.value.length !== 10) {
                alert("Please enter exactly 10 digits.");
                // wait until after alert closes
                setTimeout(() => this.focus(), 0);
            }
        });
    </script>
</body>

</html>