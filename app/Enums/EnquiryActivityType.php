<?php

namespace App\Enums;

enum EnquiryActivityType: string
{
    public const LogName = 'enquiries';

    case LeadCreated = 'lead_created';
    case LeadOwnerChanged = 'lead_owner_changed';
    case LeadSourceChanged = 'lead_source_changed';
    case FollowUpScheduled = 'follow_up_scheduled';
    case FollowUpCompleted = 'follow_up_completed';
    case NoteAdded = 'note_added';
    case ConvertedToMember = 'converted_to_member';
    case MarkedLost = 'marked_lost';
}
