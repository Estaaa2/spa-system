<?php
// app/Mail/DeploymentApproved.php

namespace App\Mail;

use App\Models\StaffBranchDeployment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DeploymentApproved extends Mailable
{
    use Queueable, SerializesModels;

    public StaffBranchDeployment $deployment;

    public function __construct(StaffBranchDeployment $deployment)
    {
        $this->deployment = $deployment;
    }

    public function build()
    {
        return $this->subject('You have been deployed to a new branch')
            ->view('emails.deployment-approved');
    }
}
