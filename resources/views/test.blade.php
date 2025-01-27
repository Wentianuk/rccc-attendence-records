<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CompreFace Test</title>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .video-container {
            margin-bottom: 20px;
        }
        #video {
            width: 100%;
            max-width: 640px;
            margin-bottom: 10px;
        }
        .button {
            padding: 10px 20px;
            margin: 5px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .button:hover {
            background-color: #45a049;
        }
        #result {
            margin-top: 20px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>CompreFace Test</h1>
        
        <div class="video-container">
            <video id="video" autoplay></video>
            <canvas id="canvas" style="display:none;"></canvas>
            <div>
                <button class="button" id="captureBtn">Capture Image</button>
                <button class="button" id="testRecognitionBtn">Test Recognition</button>
            </div>
        </div>

        <div id="result"></div>
    </div>

    <script>
        // Get video element
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const captureBtn = document.getElementById('captureBtn');
        const testRecognitionBtn = document.getElementById('testRecognitionBtn');
        const resultDiv = document.getElementById('result');

        // Access webcam
        async function initCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                video.srcObject = stream;
            } catch (err) {
                console.error('Error accessing camera:', err);
                resultDiv.innerHTML = 'Error accessing camera: ' + err.message;
            }
        }

        // Initialize camera when page loads
        initCamera();

        // Capture image and test face recognition
        async function captureAndTest(endpoint) {
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            const context = canvas.getContext('2d');
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            
            // Convert canvas to blob
            canvas.toBlob(async (blob) => {
                const formData = new FormData();
                formData.append('image', blob, 'capture.jpg');

                try {
                    const response = await axios.post(endpoint, formData);
                    resultDiv.innerHTML = '<pre>' + JSON.stringify(response.data, null, 2) + '</pre>';
                } catch (error) {
                    resultDiv.innerHTML = '<pre>Error: ' + JSON.stringify(error.response?.data || error.message, null, 2) + '</pre>';
                }
            }, 'image/jpeg');
        }

        // Event listeners
        captureBtn.addEventListener('click', () => {
            captureAndTest('/test-face-capture');
        });

        testRecognitionBtn.addEventListener('click', () => {
            captureAndTest('/test-compreface');
        });
    </script>
</body>
</html> 