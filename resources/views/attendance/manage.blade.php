<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Members - RCCC Attendance System</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold">Manage Members</h1>
            <a href="{{ route('attendance.index') }}" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                Back to Attendance
            </a>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2 text-left">Photo</th>
                            <th class="px-4 py-2 text-left">Name</th>
                            <th class="px-4 py-2 text-left">Email</th>
                            <th class="px-4 py-2 text-left">Phone</th>
                            <th class="px-4 py-2 text-left">Face ID</th>
                            <th class="px-4 py-2 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($members as $member)
                        <tr id="member-row-{{ $member->id }}">
                            <td class="border px-4 py-2">
                                @if($member->photo)
                                    <img src="{{ asset('storage/' . $member->photo) }}" 
                                         alt="{{ $member->full_name }}"
                                         class="w-24 h-24 object-cover">
                                @else
                                    <div class="w-24 h-24 bg-gray-200 flex items-center justify-center">
                                        <span class="text-gray-500">No Photo</span>
                                    </div>
                                @endif
                            </td>
                            <td class="border px-4 py-2">{{ $member->full_name }}</td>
                            <td class="border px-4 py-2">{{ $member->email ?: 'N/A' }}</td>
                            <td class="border px-4 py-2">{{ $member->phone ?: 'N/A' }}</td>
                            <td class="border px-4 py-2">
                                <span class="text-sm text-gray-600">{{ $member->face_id }}</span>
                            </td>
                            <td class="border px-4 py-2">
                                <button 
                                    onclick="deleteMember({{ $member->id }}, '{{ $member->full_name }}')"
                                    class="bg-red-500 text-white py-1 px-3 rounded hover:bg-red-600 text-sm">
                                    Delete
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden flex items-center justify-center">
        <div class="bg-white p-6 rounded-lg shadow-lg max-w-sm w-full mx-4">
            <h3 class="text-lg font-semibold mb-4">Confirm Deletion</h3>
            <p id="confirmMessage" class="mb-6"></p>
            <div class="flex justify-end space-x-4">
                <button onclick="hideConfirmModal()" class="bg-gray-500 text-white py-2 px-4 rounded hover:bg-gray-600">
                    Cancel
                </button>
                <button id="confirmDeleteBtn" class="bg-red-500 text-white py-2 px-4 rounded hover:bg-red-600">
                    Delete
                </button>
            </div>
        </div>
    </div>

    <script>
        let memberToDelete = null;
        const confirmModal = document.getElementById('confirmModal');
        const confirmMessage = document.getElementById('confirmMessage');
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');

        function showConfirmModal() {
            confirmModal.classList.remove('hidden');
        }

        function hideConfirmModal() {
            confirmModal.classList.add('hidden');
            memberToDelete = null;
        }

        function deleteMember(memberId, memberName) {
            memberToDelete = memberId;
            confirmMessage.textContent = `Are you sure you want to delete ${memberName}? This action cannot be undone.`;
            showConfirmModal();
        }

        confirmDeleteBtn.addEventListener('click', async () => {
            if (!memberToDelete) return;

            try {
                const response = await axios.delete(`/attendance/members/${memberToDelete}`);
                if (response.data.status === 'success') {
                    // Remove the member row from the table
                    const row = document.getElementById(`member-row-${memberToDelete}`);
                    row.remove();
                    hideConfirmModal();
                }
            } catch (error) {
                alert('Error deleting member. Please try again.');
                console.error('Error:', error);
            }
        });
    </script>
</body>
</html> 