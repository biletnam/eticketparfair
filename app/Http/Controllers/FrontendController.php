<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use Mail;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Order;
use App\Confirmation;
use App\Ticket;
use App\Repositories\OrderRepositories;
use App\Repositories\TypeRepositories;
use App\Repositories\TicketRepositories;

class FrontendController extends Controller
{
    protected $orders;
    protected $types;
    protected $tickets;

    /**
    * Create a new controller instance.
    *
    * @param  OrderRepositories  $orders
    * @return void
    */
    public function __construct(OrderRepositories $orders, TypeRepositories $types, TicketRepositories $tickets){
        $this->orders = $orders;
        $this->types = $types;
        $this->tickets = $tickets;
    }

    public function welcome(){
        return view('frontend.welcome');
    }

    public function buy(){
        $remaining_tickets = $this->tickets->countTicketsRemaining(1);
        return view('frontend.register', [
            'remaining_tickets' => $remaining_tickets,
        ]);
    }

    public function confirmation(){
        return view('frontend.confirmation');
    }

    public function buyStore(Request $request){

        $this->validate($request, [
            'type_id' => 'required|integer',
            'name' => 'required',
            'email' => 'required|unique:orders|email',
            'id_no' => 'required',
            'quantity' => 'required|integer|min:1|max:3',
        ]);

        //find type
        $type = $this->types->findById($request->type_id);

        $remaining_tickets = $this->tickets->countTicketsRemaining($type->id);

        if($remaining_tickets < $request->quantity){
            return redirect('/buy')->with('error_message', 'Number of tickets remaining: <span id="first">' . $remaining_tickets . '</span>');
        }

        //save order
        $order = new Order;
        $order->fill($request->all());
        $order->no_order = $this->orders->generateNoOrder();
        $order->expired_date = date('Y-m-d H:i:s', time() + (3600 * 10)); //10 hours
        $order->status = 1;
        $order->total_price = ($type->price * $order->quantity) + rand(1, 999);
        $order->save();

        //TODO: make safer saving technique with transaction
        //set to ticket
        $tickets = Ticket::where('type_id', $type->id)
                        ->where('order_date', NULL)
                        ->limit($order->quantity)
                        ->get();

        foreach($tickets as $ticket){
            $ticket->order()->associate($order);
            $ticket->order_date = date('Y-m-d H:i:s');
            $ticket->save();
        }

        Mail::send('emails.order', ['order' => $order], function($m) use ($order){
            $m->from('wilianto.indra@gmail.com', 'Parahyangan Fair');
            $m->to($order->email, $order->name);
            $m->subject('Thank you for order');
        });

        return view('frontend.register_success', [
            'email' => $order->email,
        ]);
    }

    public function confirmationStore(Request $request){
        $this->validate($request,[
            'no_rekening' => 'required',
            'nama_bank' => 'required',
            'name' => 'required',
            'total_transfer' => 'required',
            'no_order' => 'required',
        ]);

        $order = $this->orders->findByNo($request->no_order);

        if($order === null){
            return redirect('/confirmation')->with('error_message', 'Order <b id="fourth">#' . $request->no_order . '</b> not found');
        }elseif($order->status == Order::STATUS_EXPIRE){
            return redirect('/confirmation')->with('error_message', 'Order <b id="fourth">#' . $request->no_order . '</b> was expire');
        }elseif($order->status == Order::STATUS_PAID){
            return redirect('/confirmation')->with('error_message', 'Order <b id="fourth">#' . $request->no_order . '</b> was paid');
        }

        $confirmation = new Confirmation;
        $confirmation->order_id = $order->id;
        $confirmation->no_rekening = $request->no_rekening;
        $confirmation->nama_bank = $request->nama_bank;
        $confirmation->total_transfer = $request->total_transfer;
        $confirmation->save();

        return view('frontend.confirmation_success', [
            'email' => $confirmation->order->email,
        ]);
    }
}
