<?php

use Livewire\Volt\Component;

new class extends Component
{
    public $user;

    public function mount($user)
    {
        $this->user = $user;
    }
}

?>

<div>
    {{--  Email History Table  --}}
    @livewire(admin_view_path('livewire.table'), [
        'title' => __('messages.email_history'),
        'class' => '',
        'entries' => 15,
        'columns' => [
            __('messages.id'),
            __('messages.subject'),
            __('messages.to'),
            __('messages.status'),
            __('messages.created_at'),
            '',
        ],
        'sortableColumns' => [
            __('messages.id'),
            __('messages.user'),
            __('messages.subject'),
            __('messages.from'),
            __('messages.to'),
            __('messages.status'),
            __('messages.updated_at'),
            __('messages.created_at'),
        ],
        'rows' => $user->emails()->where('display', 1)->latest()->get()->map(function ($extension) {
            return [
                $extension->id,
                Str::limit($extension->subject, 50),
                $extension->to,
                $extension->status == 'delivered' ? '<span class="badge bg-green-lt">Delivered</span>' : ($extension->status == 'read' ? '<span class="badge bg-info-lt">Read</span>' : ($extension->status == 'failed' ? '<span class="badge bg-danger-lt">Failed</span>' : '<span class="badge bg-warning-lt">' . ucfirst($extension->status) . '</span>')),
                $extension->created_at->translatedFormat('d M Y H:i'),
                '<a href="' . route('admin.emails.view', $extension->id) . '" target="_blank">' . __('messages.view') . '</a>'
            ];
        })->toArray(),
    ])
</div>
