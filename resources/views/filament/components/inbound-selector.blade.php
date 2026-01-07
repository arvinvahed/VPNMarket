<div class="space-y-2 max-h-96 overflow-y-auto p-1">
    @foreach($inbounds as $inbound)
        <div
            wire:click="$set('data.inbound_id', {{ $inbound['id'] }}); $dispatch('close-modal', {id: 'selectInbound'});"
            class="p-4 border rounded-xl hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer flex justify-between items-center transition duration-200 group"
        >
            <div>
                <div class="font-bold text-sm text-gray-800 dark:text-gray-200 group-hover:text-primary-600">
                    {{ $inbound['remark'] ?? 'بدون نام' }}
                </div>
                <div class="text-xs text-gray-500 mt-1">
                    <span class="bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">{{ strtoupper($inbound['protocol']) }}</span>
                    <span class="ml-2">Port: {{ $inbound['port'] }}</span>
                </div>
            </div>
            <div class="bg-primary-50 text-primary-700 text-xs px-3 py-1.5 rounded-lg font-mono">
                ID: {{ $inbound['id'] }}
            </div>
        </div>
    @endforeach
</div>
