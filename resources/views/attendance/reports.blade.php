<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RCCC Attendance Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold text-center mb-8">RCCC Attendance Reports</h1>

        <!-- Filters -->
        <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
            <form class="flex flex-wrap gap-4">
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Date</label>
                    <input type="date" name="date" value="{{ $date }}" 
                           class="w-full rounded-md border-gray-300 shadow-sm">
                </div>
                <div class="flex-1">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Event Type</label>
                    <select name="event_type" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="sunday_service" {{ $eventType === 'sunday_service' ? 'selected' : '' }}>
                            Sunday Service
                        </option>
                        <option value="bible_study" {{ $eventType === 'bible_study' ? 'selected' : '' }}>
                            Bible Study
                        </option>
                        <option value="prayer_meeting" {{ $eventType === 'prayer_meeting' ? 'selected' : '' }}>
                            Prayer Meeting
                        </option>
                    </select>
                </div>
                <div class="flex items-end">
                    <button type="submit" class="bg-blue-500 text-white py-2 px-4 rounded hover:bg-blue-600">
                        Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Attendance List -->
        <div class="bg-white p-6 rounded-lg shadow-lg">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold">Attendance Records</h2>
                <button id="clearAllBtn" 
                        class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    Clear All Records
                </button>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full table-auto">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2">Name</th>
                            <th class="px-4 py-2">Email</th>
                            <th class="px-4 py-2">Phone</th>
                            <th class="px-4 py-2">Check-in Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($attendance as $record)
                        <tr>
                            <td class="border px-4 py-2">{{ $record->member->full_name }}</td>
                            <td class="border px-4 py-2">{{ $record->member->email }}</td>
                            <td class="border px-4 py-2">{{ $record->member->phone }}</td>
                            <td class="border px-4 py-2">{{ $record->check_in_time->format('H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Summary -->
            <div class="mt-6">
                <h3 class="text-lg font-semibold mb-2">Summary</h3>
                <p>Total Attendance: {{ $attendance->count() }}</p>
            </div>
        </div>

        <!-- Back to Attendance -->
        <div class="mt-8 text-center">
            <a href="{{ route('attendance.index') }}" class="text-blue-500 hover:text-blue-700">
                &larr; Back to Attendance
            </a>
        </div>
    </div>

    <script>
        document.getElementById('clearAllBtn').addEventListener('click', async function() {
            if (!confirm('Are you sure you want to delete ALL attendance records? This action cannot be undone.')) {
                return;
            }

            try {
                const response = await fetch('{{ route("attendance.clear.all") }}', {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    }
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // Clear the table
                    document.querySelector('tbody').innerHTML = '';
                    // Show success message
                    alert('All records have been cleared successfully.');
                } else {
                    alert(result.message || 'Error clearing records.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error clearing records. Please try again.');
            }
        });
    </script>
</body>
</html> 