<?php

namespace App\Forms;

use Nette,
	Nette\Application\UI\Form,
	Nette\Security\User;
use App\Model\GossipToken\GossipToken;
use App\Model\GossipManager;
use Nette\Database\Context;


class GossipFormFactory extends BaseFormFactory
{
    /** @var User */
    private $user;
    
    /** @var GossipToken */
    private $token;
    
    /** @var GossipManager */
    private $manager;
    
    /** @var Context */
    private $database;

    public function __construct(User $user, GossipToken $token, GossipManager $manager, Context $database)
    {
	$this->user = $user;
        $this->token = $token;
        $this->manager = $manager;
        $this->database = $database;
    }
    
    public function createGossipForm() {
        $persons = $this->createPersonList();
        
        $form = parent::create();
        $form->addMultiSelect('authors', 'Autoři:', $persons)
                ->setRequired('Musí být vyplněn alespoň jeden autor.')
                ->setDefaultValue($this->getLoggedPersonId())
                ->addRule(array($this, 'loggedAuthorFilledValidator'), 'Přihlášený člověk musí být autorem.', $this->getLoggedPersonId())
                ->setAttribute('class','authors');
        
        $form->addMultiSelect('victims', 'Oběti:', $persons)
                ->setRequired('Musí být vyplněna alespoň jedna oběť.')
                 ->setAttribute('class','victims');
        $form->addTextArea('gossip', 'Text drbu:')
                ->addRule(Form::MAX_LENGTH, 'Text drbu smí být dlouhý maximálně %d znaků.', 65535)
                ->setRequired('Text drbu nesmí být prázdný')
                ->setAttribute('placeholder', 'Zde napiš text drbu.');
        $form->addSubmit('submit', 'Odeslat');
        $form->addButton('null','původní hodnoty')
                ->setAttribute('type', 'reset')
                ->setAttribute('class','reset');
        $form->addProtection('Vypršel časový limit, odešlete formulář znovu.');
        $form->onSuccess[] = array($this, 'gossipFormSucceeded');
        return $form;
    }
    
    public function loggedAuthorFilledValidator($item, $arg) {
        return in_array($arg, $item->value);
    }
        
    public function gossipFormSucceeded(Form $form, $values)
    {
        if (!$this->user->isAllowed('gossip', 'add')) {
            $form->getPresenter()->error('Nemáte oprávnění pro přidání drbu.', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        $this->token->generateToken();
        
        $this->manager->add($values->gossip, $values->authors, $values->victims);
    }
    
    private function createPersonList() {
        $types = array(
            'pako' => 'Účastníci',
            'org' => 'Organizátoři',
            'visit' => 'Návštěva',
        );
        $persons = array();
        
        foreach ($types as $personType => $personGroup) {
            $persQuery = $this->database->table('person')->where('person_type', $personType);
            $pers = array();
            foreach ($persQuery as $person) {
                
//                $name = $person->display_name;
//                if($name === null) {
//                    $name = $person->other_name .' '. $person->family_name;
//                }
                $pers[$person->person_id] = $this->manager->getPersonDisplayName($person);
            }
            $persons[$personGroup] = $pers;
        }        
        return $persons;
    }
    
    private function getLoggedPersonId() {
        $person = $this->database->table('person')->where(':login.login_id', $this->user->id)->fetch();
        if(!$person) {
            return null;
        }
        else {
            return $person->person_id;
        }
    }
    
    public function createApproveForm() {
        $form = parent::create();
        $options = array(
            'new' => 'Ponechat neschválený',
            'approved' => 'Schválit',
            'rejected' => 'Zamítnout',
            'duplicit' => 'Duplicitní',
        );
        
        $gossips = $this->manager->getByStatus('new');
        foreach($gossips as $gossip) {
            $form->addRadioList($gossip->gossip_id, $gossip->gossip, $options)
                ->setDefaultValue('new');
        }
        $form->addProtection('Vypršel časový limit, odešlete formulář znovu.');
        $form->addSubmit('submit', 'Odeslat');
        $form->onSuccess[] = array($this, 'approveFormSucceeded');
        return $form;
    }
    
    public function approveFormSucceeded(Form $form, $values) {
        if (!$this->user->isAllowed('gossip', 'approve')) {
            $form->getPresenter()->error('Nemáte oprávnění pro schvalování drbů.', \Nette\Http\IResponse::S403_FORBIDDEN);
        }
        
        foreach($values as $gossipId => $status) {
            if($status === 'new') {
                continue;
            }
            $this->manager->changeStatus($gossipId, $status);
        }
    }
}