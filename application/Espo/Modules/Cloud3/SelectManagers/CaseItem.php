<?php

namespace Espo\Modules\Cloud3\SelectManagers;

class CaseItem extends \Espo\Core\SelectManagers\Base
{
    protected $parentTable = 'case';

    protected $parentEntityType = 'Case';

    protected $parentIdAttribute = 'caseId';

    protected $parentLink = 'case';

    protected function accessOnlyOwn(&$result)
    {
        $this->addJoin([
            $this->parentLink,
            $this->parentLink . 'Access',
        ], $result);

        $result['whereClause'][] = [
            $this->parentLink . 'Access.assignedUserId' => $this->getUser()->id
        ];
    }

    protected function accessOnlyTeam(&$result)
    {
        $teamIdList = $this->user->getLinkMultipleIdList('teams');
        if (empty($teamIdList)) {
            $this->accessOnlyOwn($result);
            return;
        }
        $arr = [];

        $parentAlias = $this->parentLink . 'Access';

        $this->addJoin([
            $this->parentLink,
            $parentAlias,
        ], $result);

        $this->addJoin([
            'EntityTeam',
            'teamsAccess',
            [
                'entityId:' => $parentAlias . '.id',
                'entityType' => $this->parentEntityType,
                'deleted' => 0,
            ]
        ], $result);

        $result['whereClause'][] = [
            'OR' => [
                $parentAlias . '.assignedUserId' => $this->getUser()->id,
                'teamsAccess.teamId' => $teamIdList,
            ]
        ];

        $this->setDistinct(true, $result);
    }

    protected function accessPortalOnlyOwn(&$result)
    {
        $result['whereClause'][] = array(
            'id' => null
        );
    }

    protected function accessPortalOnlyContact(&$result)
    {
        $result['whereClause'][] = array(
            'id' => null
        );
    }

    protected function accessPortalOnlyAccount(&$result)
    {
        $result['whereClause'][] = array(
            'id' => null
        );
    }
}
