<?php

namespace Espo\Custom\Controllers;

class Call extends \Espo\Modules\Crm\Controllers\Call
{
    public function actionGetContact($params, $data, $request)
    {
        $entityManager = $this->getEntityManager();
        $data = array (
            "accountId" => '',
            "accountName" => '',
            "contactId" => '',
            "contactName" => '',
        );
        if(strlen($request->get('phoneNumber')) >= 8) {
            $contactList = $entityManager->getRepository('Contact')->where([
                'phoneNumber*' => '%'.substr($request->get('phoneNumber'), -8).'%'
            ])->find();
            if(count($contactList) > 0) {
                $GLOBALS['log']->debug('N. contatti:', [count($contactList)]);
                $account = $contactList[0]->get('account');
                $data['accountId'] = $account->get('id');
                $data['accountName'] = $account->get('name');
                $GLOBALS['log']->debug('N. contatti:', [count($contactList)]);
                if (count($contactList) == 1) {
                    $data['contactId'] = $contactList[0]->get('id');
                    $data['contactName'] = $contactList[0]->get('name');
                }
            } else {
                $accountList = $entityManager->getRepository('Account')->where([
                    'phoneNumber*' => '%'.substr($request->get('phoneNumber'), -8).'%'
                ])->find();
                if (count($accountList) == 1) {
                    $data['accountId'] = $accountList[0]->get('id');
                    $data['accountName'] = $accountList[0]->get('name');
                }
            }
        }
        return $data;
    }
}
