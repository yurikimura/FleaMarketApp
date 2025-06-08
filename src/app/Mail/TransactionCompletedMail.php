<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use App\Models\Item;
use App\Models\User;

class TransactionCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $item;
    public $buyer;
    public $seller;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(Item $item, User $buyer, User $seller)
    {
        $this->item = $item;
        $this->buyer = $buyer;
        $this->seller = $seller;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('取引が完了しました - ' . $this->item->name)
                    ->view('emails.transaction-completed')
                    ->with([
                        'itemName' => $this->item->name,
                        'itemPrice' => $this->item->price,
                        'buyerName' => $this->buyer->name,
                        'sellerName' => $this->seller->name,
                    ]);
    }
}
