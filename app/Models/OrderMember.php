<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderMember extends Model
{
    protected $table = 'order_members';

    protected $fillable = [
        'order_id',
        'user_id',
        'email',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getEmailAttribute($value)
    {
        if ($this->user) {
            return $this->user->email;
        }

        return $value;
    }

    public function sendEmailNotification()
    {
        $emailData = [
            'mailable_type' => Order::class,
            'mailable_id' => $this->order_id,
            'user_id' => $this->user_id,
            'to' => $this->email,
            'subject' => "You have been invited to manage {$this->order->package->name}",
            'lines' => [
                "You have been added as a member to the order for {$this->order->package->name}.",
                "If you don't have an account, you can create one using the email: {$this->email}.",
                'The invitation will appear in your account once you log in.',
            ],
            'button_text' => 'View Invite',
            'button_url' => route('dashboard.order-invites'),
        ];

        Email::create($emailData);
    }

    public function sendAcceptionEmailNotification($user)
    {
        $emailData = [
            'mailable_type' => Order::class,
            'mailable_id' => $this->order_id,
            'user_id' => $this->order->user_id,
            'to' => $this->order->user->email,
            'subject' => "{$user->username} has accepted your invite to manage {$this->order->package->name}",
            'lines' => [
                "{$user->username} has accepted your invite to manage {$this->order->package->name}.",
                'Members can be viewed in the order details or with the button below.',
            ],
            'button_text' => 'View Members',
            'button_url' => route('orders.view.members', ['order' => $this->order_id]),
        ];

        Email::create($emailData);
    }
}
