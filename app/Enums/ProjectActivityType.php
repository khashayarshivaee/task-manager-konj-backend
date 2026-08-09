<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectActivityType: string
{
    case ProjectCreated =
        'project_created';

    case ProjectUpdated =
        'project_updated';

    case ProjectMemberAdded =
        'project_member_added';

    case ProjectMemberRemoved =
        'project_member_removed';

    case TaskCreated =
        'task_created';

    case TaskUpdated =
        'task_updated';

    case TaskStatusChanged =
        'task_status_changed';

    case TaskAssigneesChanged =
        'task_assignees_changed';

    case TaskDeleted =
        'task_deleted';

    case CommentCreated =
        'comment_created';

    case CommentUpdated =
        'comment_updated';

    case CommentDeleted =
        'comment_deleted';

    case TaskAttachmentAdded =
        'task_attachment_added';

    case TaskAttachmentRemoved =
        'task_attachment_removed';

    case CommentAttachmentAdded =
        'comment_attachment_added';

    case CommentAttachmentRemoved =
        'comment_attachment_removed';
}
