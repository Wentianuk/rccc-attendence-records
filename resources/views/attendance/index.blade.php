<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCCC Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">RCCC Attendance System</h1>
            <div class="space-x-4">
                <a href="{{ route('attendance.manage') }}" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">
                    Manage Members
                </a>
                <a href="{{ route('attendance.reports') }}" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                    View Reports
                </a>
            </div>
        </div>

        <div class="mb-8">
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold">Face Recognition</h2>
                </div>
                
                <div class="mb-4">
                    <div class="flex gap-4">
                        <!-- Video container on the left -->
                        <div class="flex-1">
                            <video id="video" class="w-full rounded-lg shadow-sm" autoplay playsinline></video>
                            <canvas id="canvas" class="hidden"></canvas>
                        </div>
                        
                        <!-- Message container on the right -->
                        <div id="message-container" class="w-80 max-h-[500px] overflow-y-auto space-y-2 flex flex-col-reverse">
                            <!-- Messages will be added here dynamically -->
                        </div>
                    </div>
                </div>

                <div class="flex justify-center gap-4 mb-6">
                    <button id="startButton" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600">
                        Start Recognition
                    </button>
                    <button id="stopButton" class="bg-red-500 text-white px-6 py-2 rounded-lg hover:bg-red-600 hidden">
                        Stop Recognition
                    </button>
                </div>

                <!-- Registration Form -->
                <div class="border-t pt-6">
                    <form id="registrationForm" class="flex gap-4 items-end">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">First Name</label>
                            <input type="text" name="first_name" required 
                                   class="w-full h-12 rounded-md border-2 border-gray-300 shadow-sm px-4 focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Last Name</label>
                            <input type="text" name="last_name" required
                                   class="w-full h-12 rounded-md border-2 border-gray-300 shadow-sm px-4 focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                            <input type="email" name="email" 
                                   class="w-full h-12 rounded-md border-2 border-gray-300 shadow-sm px-4 focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        </div>
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Phone</label>
                            <input type="tel" name="phone" 
                                   class="w-full h-12 rounded-md border-2 border-gray-300 shadow-sm px-4 focus:border-green-500 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        </div>
                        <div class="flex-none">
                            <button type="submit" class="h-12 bg-green-500 text-white px-8 rounded-md hover:bg-green-600 text-lg">
                                Register
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Camera handling
        let video = document.getElementById('video');
        let canvas = document.getElementById('canvas');
        let startButton = document.getElementById('startButton');
        let stopButton = document.getElementById('stopButton');
        let recognitionStatus = document.getElementById('recognition-status');
        let stream = null;
        let recognitionInterval = null;
        let isProcessing = false;
        let registrationPromptShown = false;  // Add flag to track registration prompt
        
        // Get user media
        async function startVideo() {
            try {
                stream = await navigator.mediaDevices.getUserMedia({ 
                    video: { 
                        facingMode: 'user',
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    } 
                });
                video.srcObject = stream;
                registrationPromptShown = false;  // Reset flag when starting new session
            } catch (err) {
                console.error('Error accessing camera:', err);
                alert('Error accessing camera. Please make sure you have granted camera permissions.');
            }
        }

        // Stop video stream
        function stopVideo() {
            if (stream) {
                stream.getTracks().forEach(track => track.stop());
                video.srcObject = null;
            }
        }

        // Capture frame and send for recognition
        async function captureAndRecognize() {
            if (isProcessing) return;
            
            isProcessing = true;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            
            // Convert canvas to blob
            canvas.toBlob(async function(blob) {
                const formData = new FormData();
                formData.append('image', blob, 'capture.jpg');
                
                try {
                    const response = await fetch('{{ route("attendance.realtime") }}', {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    });
                    
                    const result = await response.json();
                    
                    // Handle different response statuses
                    switch(result.status) {
                        case 'success':
                            showWelcomeMessage(result.member.full_name);
                            // Play success sound
                            playSound('success');
                            break;
                            
                        case 'already_recorded':
                            // Don't show any message for already recorded
                            break;
                            
                        case 'low_confidence':
                        case 'not_found':
                            // Show registration prompt only once
                            if (!registrationPromptShown) {
                                showRegistrationPrompt();
                                registrationPromptShown = true;
                            }
                            break;
                            
                        case 'no_face':
                            // Don't show status when no face detected
                            break;
                            
                        case 'error':
                            showStatus(result.message, 'bg-red-500');
                            break;
                    }
                } catch (error) {
                    console.error('Recognition error:', error);
                } finally {
                    isProcessing = false;
                }
            }, 'image/jpeg', 0.8);
        }

        // Show status message
        function showStatus(message, bgClass = 'bg-black') {
            recognitionStatus.textContent = message;
            recognitionStatus.className = `${bgClass} bg-opacity-75 text-white p-4 rounded-lg`;
            recognitionStatus.classList.remove('hidden');
            
            // Hide status after 2 seconds
            setTimeout(() => {
                recognitionStatus.classList.add('hidden');
            }, 2000);
        }

        // Play sound effect
        function playSound(type) {
            const audio = new Audio(type === 'success' ? '/sounds/success.mp3' : '/sounds/error.mp3');
            audio.play().catch(e => console.log('Sound play error:', e));
        }

        // Start recognition
        startButton.addEventListener('click', async () => {
            await startVideo();
            recognitionInterval = setInterval(captureAndRecognize, 1000); // Check every second
            startButton.classList.add('hidden');
            stopButton.classList.remove('hidden');
        });

        // Stop recognition
        stopButton.addEventListener('click', () => {
            clearInterval(recognitionInterval);
            stopVideo();
            stopButton.classList.add('hidden');
            startButton.classList.remove('hidden');
        });

        // Registration form handling
        document.getElementById('registrationForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            // Capture photo for registration
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);

            canvas.toBlob(async (blob) => {
                const formData = new FormData(e.target);
                formData.append('image', blob, 'registration.jpg');

                try {
                    const response = await axios.post('/attendance/register', formData);
                    // Clear any existing messages in the message container
                    const messageContainer = document.getElementById('message-container');
                    messageContainer.innerHTML = '';
                    
                    // Show success message
                    showMessage('Registration successful!', 'success');
                    e.target.reset();
                    
                    // Reset registration prompt flag
                    registrationPromptShown = false;
                } catch (error) {
                    if (error.response?.data?.message) {
                        showMessage(error.response.data.message, 'error');
                    } else {
                        showMessage('Error during registration', 'error');
                    }
                }
            }, 'image/jpeg');
        });

        function showMessage(message, type) {
            attendanceMessage.textContent = message;
            attendanceMessage.className = `mt-4 p-4 rounded ${
                type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'
            }`;
            attendanceMessage.classList.remove('hidden');
            if (type === 'success') {
                setTimeout(() => {
                    attendanceMessage.classList.add('hidden');
                }, 5000);
            }
        }

        // Show welcome message in chat-box style
        function showWelcomeMessage(name) {
            const container = document.getElementById('message-container');
            
            // Create new welcome message
            const message = document.createElement('div');
            message.className = 'bg-green-500 text-white p-3 rounded-lg shadow-md text-lg welcome-message';
            message.textContent = `Welcome, ${name}!`;
            
            // Add new message at the top
            container.insertBefore(message, container.firstChild);
            
            // Only remove messages if we have more than 10
            const messages = container.children;
            if (messages.length > 10) {
                container.removeChild(messages[messages.length - 1]);
            }
        }

        // Show registration prompt
        function showRegistrationPrompt() {
            const container = document.getElementById('message-container');
            const message = document.createElement('div');
            message.className = 'bg-red-500 text-white p-4 rounded-lg shadow-md text-lg flex flex-col gap-2';
            message.textContent = 'Face not recognized. Please register.';
            
            // Remove oldest message if we have 10 messages
            const messages = container.children;
            if (messages.length >= 10) {
                container.removeChild(messages[messages.length - 1]);
            }
            
            // Add new message at the top
            container.insertBefore(message, container.firstChild);
            
            // Remove message after 5 seconds
            setTimeout(() => {
                message.remove();
            }, 5000);
            
            // Automatically scroll and highlight registration form
            document.querySelector('#registrationForm').scrollIntoView({ 
                behavior: 'smooth',
                block: 'center'
            });
            
            // Highlight registration form
            const registrationDiv = document.querySelector('#registrationForm').parentElement;
            registrationDiv.classList.add('ring-4', 'ring-red-400', 'ring-opacity-50');
            setTimeout(() => {
                registrationDiv.classList.remove('ring-4', 'ring-red-400', 'ring-opacity-50');
            }, 3000);
        }
    </script>
</body>
</html> 