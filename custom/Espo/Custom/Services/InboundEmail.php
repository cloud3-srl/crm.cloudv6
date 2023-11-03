<?php

namespace Espo\Custom\Services;

use Espo\ORM\Entity;
use Espo\Entities\Team;
use Laminas\Mail\Storage;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;

class InboundEmail extends \Espo\Services\InboundEmail
{
    protected function emailToCase(\Espo\Entities\Email $email, array $params = [])
    {
        $case = $this->getEntityManager()->getEntity('Case');
        $case->populateDefaults();
        $case->set('name', $email->get('name'));

        $bodyPlain = $email->getBodyPlain();

        if (trim(preg_replace('/\s+/', '', $bodyPlain)) === '') {
            $bodyPlain = '';
        }

        if ($bodyPlain) {
            $case->set('description', $bodyPlain);
        }

        $attachmentIdList = $email->getLinkMultipleIdList('attachments');
        $copiedAttachmentIdList = [];

        foreach ($attachmentIdList as $attachmentId) {
            $attachment = $this->getEntityManager()->getRepository('Attachment')->get($attachmentId);
            if (!$attachment) continue;
            $copiedAttachment = $this->getEntityManager()->getRepository('Attachment')->getCopiedAttachment($attachment);
            $copiedAttachmentIdList[] = $copiedAttachment->id;
        }

        if (count($copiedAttachmentIdList)) {
            $case->setLinkMultipleIdList('attachments', $copiedAttachmentIdList);
        }

        $userId = null;
        if (!empty($params['userId'])) {
            $userId = $params['userId'];
        }

        if (!empty($params['inboundEmailId'])) {
            $case->set('inboundEmailId', $params['inboundEmailId']);
        }

        $teamId = false;
        if (!empty($params['teamId'])) {
            $teamId = $params['teamId'];
        }
        if ($teamId) {
            $case->set('teamsIds', [$teamId]);
        }

        $caseDistribution = '';
        if (!empty($params['caseDistribution'])) {
            $caseDistribution = $params['caseDistribution'];
        }

        $targetUserPosition = null;
        if (!empty($params['targetUserPosition'])) {
            $targetUserPosition = $params['targetUserPosition'];
        }


        switch ($caseDistribution) {
            case 'Direct-Assignment':
                if ($userId) {
                    $case->set('assignedUserId', $userId);
                    $case->set('status', 'New');
                }
                break;
            case 'Round-Robin':
                if ($teamId) {
                    $team = $this->getEntityManager()->getEntity('Team', $teamId);
                    if ($team) {
                        $this->assignRoundRobin($case, $team, $targetUserPosition);
                    }
                }
                break;
            case 'Least-Busy':
                if ($teamId) {
                    $team = $this->getEntityManager()->getEntity('Team', $teamId);
                    if ($team) {
                        $this->assignLeastBusy($case, $team, $targetUserPosition);
                    }
                }
                break;
        }

        if ($case->get('assignedUserId')) {
            $email->set('assignedUserId', $case->get('assignedUserId'));
        }

        if ($email->get('accountId')) {
            $case->set('accountId', $email->get('accountId'));
        }

        $contact = $this->getEntityManager()->getRepository('Contact')->join([['emailAddresses', 'emailAddressesMultiple']])->where([
            'emailAddressesMultiple.id' => $email->get('fromEmailAddressId')
        ])->findOne();
        if ($contact) {
            $case->set('contactId', $contact->id);
        } else {
            if (!$case->get('accountId')) {
                $lead = $this->getEntityManager()->getRepository('Lead')->join([['emailAddresses', 'emailAddressesMultiple']])->where([
                    'emailAddressesMultiple.id' => $email->get('fromEmailAddressId')
                ])->findOne();
                if ($lead) {
                    $case->set('leadId', $lead->id);
                }
            }
        }

        $this->getEntityManager()->saveEntity($case);

        $email->set('parentType', 'Case');
        $email->set('parentId', $case->id);

        $this->getEntityManager()->saveEntity($email, [
            'skipLinkMultipleRemove' => true,
            'skipLinkMultipleUpdate' => true
        ]);

        $case = $this->getEntityManager()->getEntity('Case', $case->id);

        return $case;
    }
}
