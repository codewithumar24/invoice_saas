<ul class="divide-y divide-line">
    @foreach ($statusCounts as $status => $count)
        <li>
            <a href="{{ route('invoices.index', ['status' => $status]) }}" class="flex items-center justify-between px-4 py-2.5 text-sm hover:bg-raised sm:px-6">
                <x-badge :status="$status" />
                <span class="num font-medium">{{ $count }}</span>
            </a>
        </li>
    @endforeach
</ul>
