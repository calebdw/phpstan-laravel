<?php

namespace Tests\Rules\Data;

use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Contracts\View\View;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Factory;

const VIEW_FACTORY_MAKE = 'view-factory-make';

class FooController
{
    public function index()
    {
        return view('index');
    }

    public function existing(): View
    {
        return view('users.index');
    }

    public function existingNested(): View
    {
        return view('emails.orders.shipped');
    }

    public function notExisting(): View
    {
        return view('foo');
    }
}

class FooMailable extends Mailable
{
    public function build(): self
    {
        return $this->text('emails.mailable.markdown');
    }

    public function bar(): self
    {
        return $this->view('emails.mailable.view');
    }

    public function content(): Content
    {
        return (new Content(
            view: 'emails.mailable.content.view',
            html: 'emails.mailable.content.html',
            text: 'emails.mailable.content.text',
            markdown: 'emails.mailable.content.markdown',
        ))->view('emails.mailable.content.fluent-view')
            ->html('emails.mailable.content.fluent-html')
            ->text('emails.mailable.content.fluent-text')
            ->markdown('emails.mailable.content.fluent-markdown');
    }
}

class FooMailMessage extends MailMessage
{
    public function build(): self
    {
        return $this->view(['emails.mail-message.markdown', 'emails.mail-message.view']);
    }
}

function viewHelper(): View
{
    return view()->make(view: 'view-helper-make');
}

function viewFactory(Factory $factory): View
{
    $method = 'make';

    return $factory->$method(VIEW_FACTORY_MAKE);
}

function viewStaticMake(bool $flag): View
{
    return \Illuminate\Support\Facades\View::make($flag ? 'view-static-make' : 'index');
}

function routeView(): void
{
    Route::view('/welcome', view: 'route-view');
}

function routerView(Router $router): void
{
    $router->view('/welcome', 'route-view');
}

function mailSend(Mailer $mailer): void
{
    Mail::send(view: 'emails.mail-send.facade', data: []);
    $mailer->send('emails.mail-send.contract', []);
    Mail::to('user@example.com')->send(new FooMailable());
}

function dummyTranslationView()
{
    return view('translations');
}
