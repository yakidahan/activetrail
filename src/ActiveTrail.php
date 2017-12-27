<?php

namespace Yehudafh\ActiveTrail;

use GuzzleHttp\Client;
use InvalidArgumentException;
use GuzzleHttp\Exception\RequestException;

class ActiveTrail
{
    protected $base = 'http://webapi.mymarketing.co.il/';

    protected $key;

    protected $softGroup;

    protected $action;

    protected $fields = [
        'status'=> 'status',
        'email'=> 'email',
        'sms'=> 'sms',
        'firstName'=> 'first_name',
        'lastName'=> 'last_name',
        'street'=> 'street',
        'zipCode'=> 'zip_code',
        'city'=> 'city',
        'phone1'=> 'phone1',
        'phone2'=> 'phone2',
        'fax'=> 'fax',
        'birthday'=> 'birthday',
        'anniversary'=> 'anniversary',
        'isDoNotMail'=> 'is_do_not_mail',
        'isDeleted'=> 'is_deleted',
    ];

    protected $params;

    function __construct($config)
    {
        $this->key = $config['api_key'];

        $this->softGroup = $config['soft_group'];

        $this->fields = array_merge($this->fields, $config['fields']);
    }

    public function post($type='post')
    {
        $client = new Client();

        try {
            $response = $client->$type($this->base . $this->action, [
                'headers' => ['Authorization' => $this->key],
                'json' => $this->params
            ]);
        } catch (RequestException $e) {
            $response = $e->getResponse();
        }

        return json_decode($response->getBody(), 1);
    }

    public function addToGroup($group, $params=[])
    {
        $this->params = array_merge($this->params, $params);

        $this->action = "api/groups/$group/members";

        return $this->post();
    }

    public function importGroup($group, $params=null, $campaign=null)
    {
        $this->action = "api/contacts/Import";

        $this->params = [
            'group' => $group,
            'contacts' => $params
        ];

        if ($campaign) {
            return [
                'response' => $this->post(),
                'campaignId' => $campaign,
                'campaign' => $this->sendCampaing($campaign, array_column($params, 'email'))
            ];
        }

        return $this->post();
    }

    public function sendCampaing($id, $emails=[], $ids=[])
    {
        $this->action = "api/campaigns/{$id}/Contacts";

        $this->params = [
            'contacts_ids' => $ids,
            'contacts_emails' => $emails
        ];

        return $this->post('put');
    }

    public function addToGroups($groups=[], $params=[])
    {
        $this->params = array_merge($this->params, $params);

        foreach ($groups as $group) {
            $this->action = "api/groups/$group/members";

            $data[] = $this->post();
        }

        return $data;
    }

    public function removeFromGroup($group, $params=[])
    {
        $this->params = array_merge($this->params, $params);

        $member = $this->getIdByEmail($this->params['email'])['id']?? null;

        if (!$member) {
            return;
        }

        $this->action = "api/groups/$group/members/$member";

        return $this->post('delete');
    }

    public function removeFromGroups($groups=[], $params=[])
    {
        $this->params = array_merge($this->params, $params);

        $member = $this->getIdByEmail($this->params['email'])['id']?? null;

        if (!$member) {
            return;
        }

        foreach ($groups as $group) {
            $this->action = "api/groups/$group/members/$member";

            $data[] = $this->post('delete');
        }

        return $data;
    }

    public function update($params=[], $campaign=null)
    {
        $this->action = "api/contacts";

        $this->params = array_merge($this->params, $params);

        if ($campaign) {
            $email = $this->params['email'];

            return [
                'response' => $this->post(),
                'campaignId' => $campaign,
                'campaign' => $this->sendCampaing($campaign, [$email])
            ];
        }

        return $this->post();
    }

    public function getIdByEmail($email)
    {
        return $this->email($email)->update();
    }

    public function updateEmail($email, $newEmail)
    {
        $id = $this->getIdByEmail($email)['id']?? null;

        if (!$id) {
            return;
        }

        $this->action = "api/contacts/{$id}";

        return $this->email($newEmail)->post('put');
    }

    public function fullname($name)
    {
        $name = explode(' ', $name);

        $this->firstName($name[0]);

        unset($name[0]);

        $this->lastName(implode(' ', $name));

        return $this;
    }

    public function subscribed()
    {
        $this->action = "api/contacts";

        return $this->status('Subscribed')->post();
    }

    public function unsubscribed()
    {
        $this->action = "api/contacts";

        if ($this->softGroup) {
            return $this->softUnsubscribed();
        }

        return $this->status('Unsubscribed')->post();
    }

    public function softUnsubscribed()
    {
        $id = $this->getIdByEmail($this->params['email'])['id'];

        $this->action = "api/contacts/{$id}/groups";

        $groups = array_column($this->post('get'), 'id');

        $this->removeFromGroups($groups, ['status' => 'Subscribed']);

        $response = $this->addToGroup($this->softGroup);

        $response['state'] = 'Soft' . $response['state'];

        $response['groupsRemoved'] = $groups;

        return $response;
    }

    public function __call($name, $arguments)
    {
        if (! array_key_exists($name, $this->fields)) {
            throw new InvalidArgumentException("Call to undefined method {$name}().");
        }

        $this->params[$this->fields[$name]] = $arguments[0];

        return $this;
    }
}
