<?php

namespace Espo\Custom\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\NotFound;

use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

class EmailNotification extends \Espo\Services\EmailNotification
{
    public function notifyAboutAssignmentJob($data)
    {
        if (empty($data->userId)) return;
        if (empty($data->assignerUserId)) return;
        if (empty($data->entityId)) return;
        if (empty($data->entityType)) return;

        $userId = $data->userId;
        $assignerUserId = $data->assignerUserId;
        $entityId = $data->entityId;
        $entityType = $data->entityType;

        $user = $this->getEntityManager()->getEntity('User', $userId);

        if (!$user) return;

        if ($user->isPortal()) return;

        $preferences = $this->getEntityManager()->getEntity('Preferences', $userId);
        if (!$preferences) return;
        if (!$preferences->get('receiveAssignmentEmailNotifications')) return;

        $ignoreList = $preferences->get('assignmentEmailNotificationsIgnoreEntityTypeList') ?? [];
        if (in_array($entityType, $ignoreList)) return;

        $assignerUser = $this->getEntityManager()->getEntity('User', $assignerUserId);
        $entity = $this->getEntityManager()->getEntity($entityType, $entityId);
        if (!$entity) return true;
        if (!$assignerUser) return true;

        $this->loadParentNameFields($entity);

        if (!$entity->hasLinkMultipleField('assignedUsers')) {
            if ($entity->get('assignedUserId') !== $userId) return true;
        }

        $emailAddress = $user->get('emailAddress');
        if (!empty($emailAddress)) {
            $email = $this->getEntityManager()->getEntity('Email');

            $subjectTpl = $this->getTemplateFileManager()->getTemplate('assignment', 'subject', $entity->getEntityType());
            $bodyTpl = $this->getTemplateFileManager()->getTemplate('assignment', 'body', $entity->getEntityType());

            $subjectTpl = str_replace(["\n", "\r"], '', $subjectTpl);

            $recordUrl = rtrim($this->getConfig()->get('siteUrl'), '/') . '/#' . $entity->getEntityType() . '/view/' . $entity->id;

            $data = [
                'userName' => $user->get('name'),
                'assignerUserName' => $assignerUser->get('name'),
                'recordUrl' => $recordUrl,
                'entityType' => $this->getLanguage()->translate($entity->getEntityType(), 'scopeNames')
            ];
            $data['entityTypeLowerFirst'] = Util::mbLowerCaseFirst($data['entityType']);

            if($entity->get('account')) {
                $data['accountName'] = $entity->get('account')->get('name');
            }
            if($entity->get('contact')) {
                $data['contactName'] = $entity->get('contact')->get('name');
            }

            $GLOBALS['log']->debug('Here is my variable:', [$data]);

            $subject = $this->getHtmlizer()->render($entity, $subjectTpl, 'assignment-email-subject-' . $entity->getEntityType(), $data, true);
            $body = $this->getHtmlizer()->render($entity, $bodyTpl, 'assignment-email-body-' . $entity->getEntityType(), $data, true);

            $email->set([
                'subject' => $subject,
                'body' => $body,
                'isHtml' => true,
                'to' => $emailAddress,
                'isSystem' => true,
                'parentId' => $entity->id,
                'parentType' => $entity->getEntityType()
            ]);
            try {
                $this->getMailSender()->send($email);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('EmailNotification: [' . $e->getCode() . '] ' .$e->getMessage());
            }
        }

        return true;
    }
}
