<?php

namespace App\Presenters;

use Nette,
	App\Model;
use Nette\Application\UI;
use App\Model\GossipToken\GossipToken;
use App\Forms\GossipFormFactory;
use App\Model\GossipManager;
use Nette\Application\Responses\JsonResponse;
use App\Model\AnimatedGossip\AnimatedGossipFactory;

/**
 * Gossip presenter.
 */
class GossipPresenter extends BasePresenter
{
    /** @var GossipFormFactory @inject */
    public $factory;
    
    /** @var Nette\Database\Context */
    private $database;
    
    /** @var GossipToken */
    private $token;
    
    /** @var GossipManager */
    private $model;
    
    /** @var AnimatedGossipFactory @inject */
    public $aniGossFactory;

    public function __construct(Nette\Database\Context $database, GossipToken $token, GossipManager $model)
    {
        parent::__construct();
        $this->database = $database;
        $this->token = $token;
        $this->model = $model;
    }    

    public function renderDefault()
    {
        $this->template->remainingCD = $this->token->getRemainingCooldown()->format('%R %i:%s');
    }
    
    public function actionApprove(){
        if (!$this->getUser()->isAllowed('gossip', 'approve')) {
            $this->error('Nemáte oprávnění ke schvalování drbů', \Nette\Http\IResponse::S401_UNAUTHORIZED);
        }
    }
    
    protected function createComponentApproveForm() {
        $form = $this->factory->createApproveForm();
	$form->onSuccess[] = function ($form) {        
            $form->getPresenter()->flashMessage('Schválení proběhlo úspěšně.', 'success');
            $form->getPresenter()->redirect('Gossip:approve');
	};
	return $form;
    }
    
    protected function createComponentGossipForm() {
        $form = $this->factory->createGossipForm();
	$form->onSuccess[] = function ($form) {        
            $form->getPresenter()->flashMessage('Gratulujeme k odeslání drbu.', 'success');
            $form->getPresenter()->redirect('Gossip:');
	};
	return $form;
    }
    
    public function actionAjax() {
        $drb= 'ahoj svet tu bude nejaký drb ktorý sa tam vykreslí!! a treba sem jebnut ešte nejaké dlašie pičoviny';
        
        $previousId = $this->request->getPost('id');
        $new_drb = $this->aniGossFactory->create($previousId);
        $request = '<div class="drb">' . $new_drb->getParsed() . '</div>';
        $id = $new_drb->getLength();
        
        //if($this->isAjax()) {
            $this->sendResponse(new JsonResponse(array('html' => $request, 'id' => $id)));
        //}
    }

}