<?php

declare(strict_types=1);

namespace App\Enums;

enum ProjectActivitySubjectType: string
{
    case Project =
        'project';

    case ProjectMember =
        'project_member';

    case Task =
        'task';

    case Comment =
        'comment';

    case TaskAttachment =
        'task_attachment';

    case CommentAttachment =
        'comment_attachment';
}
