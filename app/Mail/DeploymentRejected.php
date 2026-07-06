<?php
// app/Mail/DeploymentRejected.php

namespace App\Mail;

use App\Models\StaffBranchDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeploymentRejected extends Mailable
{
    use Queueable, SerializesModels;

    public StaffBranchDeployment $deployment;

    public function __construct(StaffBranchDeployment $deployment)
    {
        $this->deployment = $deployment;
    }

    public function build()
    {
        return $this->subject('Update on your branch deployment request')
            ->view('emails.deployment-rejected');
    }
}
