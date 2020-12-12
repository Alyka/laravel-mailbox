<?php

namespace BeyondCode\Mailbox\Http\Controllers;

use BeyondCode\Mailbox\Facades\Mailbox;
use BeyondCode\Mailbox\Http\Requests\ElasticEmailRequest;
use Illuminate\Routing\Controller;

class ElasticEmailController extends Controller
{
    public function __construct()
    {
        // $this->middleware('laravel-mailbox');
    }

    public function __invoke(ElasticEmailRequest $request)
    {
        Mailbox::callMailboxes($request->email());
    }
}
