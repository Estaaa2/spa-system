<?php
# app/routes/console.php
# php artisan send-mail

use Illuminate\Support\Facades\Artisan;
use Mailtrap\Helper\ResponseHelper;
use Mailtrap\MailtrapClient;
use Mailtrap\Mime\MailtrapEmail;
use Symfony\Component\Mime\Address;
use Illuminate\Support\Facades\Schedule;

Artisan::command('send-mail', function () {
    $email = (new MailtrapEmail())
        ->from(new Address('hello@demomailtrap.co', 'Mailtrap Test'))
        ->to(new Address('lingopiolo@gmail.com'))
        ->subject('You are awesome!')
        ->category('Integration Test')
        ->text('Congrats for sending test email with Mailtrap!');

    $response = MailtrapClient::initSendingEmails(
        apiKey: '<YOUR_API_TOKEN>'
    )->send($email);

    var_dump(ResponseHelper::toArray($response));
})->purpose('Send Mail');

Schedule::command('deployments:process')->dailyAt('00:05');
