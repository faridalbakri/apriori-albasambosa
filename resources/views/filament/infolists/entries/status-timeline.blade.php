@php
    $logs = $getState();
    $statusLabels = [
        'pending' => 'Pending',
        'settlement' => 'Settlement',
        'processing' => 'Processing',
        'ready_pickup' => 'Ready for Pickup',
        'shipping' => 'Shipping',
        'delivered' => 'Delivered',
        'completed' => 'Completed',
        'expire' => 'Expired',
        'cancel' => 'Cancelled',
        'failed' => 'Failed',
        'refund_pending' => 'Refund Pending',
        'refund_done' => 'Refund Done',
    ];
@endphp

<style>
    @media (prefers-reduced-motion: no-preference) {
        @keyframes timeline-pulse {
            0%, 100% { box-shadow: 0 0 0 4px color-mix(in srgb, var(--color-accent, #CA8A04) 20%, transparent); }
            50% { box-shadow: 0 0 0 8px color-mix(in srgb, var(--color-accent, #CA8A04) 15%, transparent); }
        }
    }

    .timeline-container {
        position: relative;
        padding-left: 28px;
    }

    .timeline-item {
        position: relative;
    }

    .timeline-line {
        position: absolute;
        left: -19px;
        top: 18px;
        width: 2px;
        background: var(--color-border, #e5e7eb);
    }

    .timeline-dot {
        position: absolute;
        left: -23px;
        top: 4px;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: var(--color-accent, #CA8A04);
        border: 2px solid var(--color-accent, #CA8A04);
        z-index: 1;
    }

    .timeline-dot-active {
        animation: timeline-pulse 2s ease-in-out infinite;
    }

    .timeline-label {
        font-size: 0.875rem;
        font-weight: 600;
        line-height: 1.3;
        color: var(--color-foreground, #374151);
    }

    .timeline-label-active {
        color: var(--color-accent, #CA8A04);
    }

    .timeline-meta {
        font-size: 0.75rem;
        color: #6b7280;
        margin-top: 2px;
    }
</style>

<div class="timeline-container">

    @forelse ($logs ?? [] as $log)
        @php
            $isLast = $loop->last;
            $label = $statusLabels[$log->new_status] ?? $log->new_status;
        @endphp
        <div class="timeline-item" style="padding-bottom: {{ $isLast ? '0' : '20px' }};">
            {{-- Vertical line segment between dots --}}
            @if (! $isLast)
                <div class="timeline-line" style="bottom: -2px;"></div>
            @endif

            {{-- Dot --}}
            <div class="timeline-dot {{ $isLast ? 'timeline-dot-active' : '' }}"></div>

            {{-- Content --}}
            <div class="timeline-label {{ $isLast ? 'timeline-label-active' : '' }}">
                {{ $label }}
            </div>
            <div class="timeline-meta">
                <span>{{ \Carbon\Carbon::parse($log->created_at)->format('d M Y H:i') }}</span>
                <span> · </span>
                <span>{{ $log->user?->name ?? 'System' }}</span>
            </div>
        </div>
    @empty
        <div class="text-xs" style="color: #6b7280;">No status history yet.</div>
    @endforelse
</div>
