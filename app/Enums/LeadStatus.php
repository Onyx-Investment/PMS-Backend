<?php
// app/Enums/LeadStatus.php (create this file)

namespace App\Enums;

class LeadStatus
{
    const QUALIFIED = 'qualified';
    const PROSPECT = 'prospect';
    const PROPOSAL = 'proposal';
    const NEGOTIATION = 'negotiation';
    const CLOSED = 'closed';
    const WON = 'won';
    const ABANDONED = 'abandoned';

    public static function all()
    {
        return [
            self::QUALIFIED,
            self::PROSPECT,
            self::PROPOSAL,
            self::NEGOTIATION,
            self::CLOSED,
            self::WON,
            self::ABANDONED,
        ];
    }

    public static function labels()
    {
        return [
            self::QUALIFIED => 'Qualified',
            self::PROSPECT => 'Prospect',
            self::PROPOSAL => 'Proposal',
            self::NEGOTIATION => 'Negotiation',
            self::CLOSED => 'Closed',
            self::WON => 'Won / Converted',
            self::ABANDONED => 'Abandoned',
        ];
    }
}