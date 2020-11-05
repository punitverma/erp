<?php

declare(strict_types=1);

namespace App\Controller;

use Cake\Http\Session;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\FrozenDate;
use Cake\Database\Expression\QueryExpression;
use Cake\Datasource\Exception\RecordNotFoundException;

/**
 * Users Controller
 *
 * @property \App\Model\Table\UsersTable $Users
 *
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class UsersController extends AppController
{

    public function beforeFilter(\Cake\Event\EventInterface $event)
    {
        parent::beforeFilter($event);
        // configure the login action to don't require authentication, preventing
        // the infinite redirect loop issue
        $this->Authentication->addUnauthenticatedActions(['login','logout','forgotpassword','forgotusername','setpassword','admindashborad']);
    }
   
    public function admindashboard(){
        $this->loadModel('AdminDashboard');
        $connection = ConnectionManager::get('default');
        $sql='SELECT r.`reward_point`, r.`amount`, r.`rank`, r.`gift` , w.cn FROM `rewards` r left join (SELECT reward_id,count(*) as cn from rewardwinner GROUP by reward_id ) w on r.id=w.reward_id';
        $stmt = $connection->execute($sql);
        $rewards = $stmt ->fetchAll('assoc');
        $this->set(compact('rewards'));
        
        $this->set('data',$this->AdminDashboard->get(0));
        //
      //  $u = $this->Authentication->getResult()->getData();
       // $this->set('u1',$u);
        //$u=$this->Users->newEmptyEntity();;
        //$u['password']='Fni@007';
        //$this->set('u2',$u);
        

    }
    public function distributordashboard(){
        
        
        $this->loadModel('Members');
        $this->loadModel('DistbDashboard');
        $this->loadModel('Alerts');

        $this->loadModel('AdminDashboard');
       
       
        $this->set('admindata',$this->AdminDashboard->get(0));
       
        $u = $this->Authentication->getResult()->getData();
        $dash=$this->DistbDashboard->newEmptyEntity();
        //debug(now());

        
        $connection = ConnectionManager::get('default');
        $sql='SELECT r.`reward_point`, r.`amount`, r.`rank`, r.`gift` , w.cn FROM `rewards` r left join (SELECT reward_id,count(*) as cn from rewardwinner GROUP by reward_id ) w on r.id=w.reward_id';
        $stmt = $connection->execute($sql);
        $rewards = $stmt ->fetchAll('assoc');
        $mem=$this->Members->get($u->username,['contain'=>['Kycs']]);
        try{
        $dash=$this->DistbDashboard->get($u->username);
        $sql="SELECT sum(if(`placement`='L',1,0)) my_left ,sum(if(`placement`='R',1,0)) my_right , sum(if(`placement`='L' and active='Y' ,1,0)) my_left_active ,sum(if(`placement`='R' and active='Y',1,0)) my_right_active FROM `members` WHERE `sponsor`='". $u->username ."'";
        $stmt = $connection->execute($sql);
        $result = $stmt ->fetchAll('assoc');

       // debug($result);
       // die();
         $dash->left_count= $result[0]['my_left_active'].'/'.$result[0]['my_left'];
        $dash->right_count= $result[0]['my_right_active'].'/'.$result[0]['my_right'];
        
        }catch (RecordNotFoundException $e){
            $dash=$this->DistbDashboard->newEmptyEntity();
            $dash->tm= new FrozenDate();
        }

          
        $this->set(compact('rewards'));
        $this->set('dash',$dash);
        $this->set('data',$mem);
        //$sql="SELECT * FROM alerts WHERE  role_id=". $u->role_id." and active=1 and periodfrom<= (select now()) " ;
     
         $sql="select fn_message(?) as message" ;
         $stmt = $connection->execute($sql,[$u->role_id]);
         $alerts = $stmt ->fetchAll('assoc');

        //$now=new FrozenDate();
        //$now = date('Y-m-d H:i:s'); 
        //$now = $now.'.123456';
        //$alerts=$this->Alerts->find()->where(['role_id'=>$u->role_id,'active'=>true,'periodfrom<='=> date('Y-m-d H:i:s')]);        
       //$alerts->where(function ( $exp,  $q) {
         //   return $exp->between( $q->func()->NOW() , $q->identifier('Alerts.periodfrom'),$q->identifier('Alerts.periodto'));
        //});
    
        $this->set('alerts',$alerts);

        
    }
    public function login()
    {

        $this->request->allowMethod(['get', 'post']);
        $result = $this->Authentication->getResult();

        $this->viewBuilder()->setLayout('landing');

        // regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            
            $u = $this->Authentication->getResult()->getData();
            if($u->role_id==2){
               // $this->sendSMS('8986666755','Admin Login');
   
            }

            // redirect to /pages/home after login success
            $redirect = $this->request->getQuery('redirect', [
                'controller' => 'users',
                'action' => 'menuadmin'
            ]);
            
            return $this->redirect($redirect);
        }
        // display error if user submitted and authentication failed
        if ($this->request->is('post') && !$result->isValid()) {

            $this->Flash->error(__('Invalid username or password'));
        }
    }
    public function logout()
    {
        $result = $this->Authentication->getResult();
        // regardless of POST or GET, redirect if user is logged in
        if ($result->isValid()) {
            $this->Authentication->logout();
            $session = $this->getRequest()->getSession();
            $session->delete('user');
            return $this->redirect(['controller' => 'Users', 'action' => 'login']);
        }
    }
    /**
     * Index method
     *
     * @return \Cake\Http\Response|null
     */
    public function index()
    {
        $this->paginate = [
            'contain' => ['Roles'],
        ];
        $users = $this->paginate($this->Users);

        $this->set(compact('users'));
    }


    public function menuadmin()
    {
        $u = $this->Authentication->getResult()->getData();
        if($u->role_id==2){
            
            return $this->redirect(['action' => 'admindashboard']);
        
        }
        if($u->role_id==5){
            return $this->redirect(['action' => 'distributordashboard']);
        }
    }

    function forgotusername(){
        
        $this->request->allowMethod(['get', 'post']);
        

        $this->viewBuilder()->setLayout('landing');
        $result=null;
        // regardless of POST or GET, redirect if user is logged in
        
        if ($this->request->is('post')) {
            $mobile=$this->request->getData('mobile');
            $sponsor=$this->request->getData('sponsor');
            $type=$this->request->getData('type');

            if($type=='M'){
                $this->loadModel('Members');
                $result=$this->Members->find()->where(['mobile'=>$mobile,'sponsor'=>$sponsor])->first();
            }
            else
            if($type=='F')
            {
                $this->loadModel('Frenchies');
                $result=$this->Frenchies->find()->where(['mobile'=>$mobile,'sponsor'=>$sponsor])->first();
            }

            if(empty($result)){
                $this->Flash->error(__('Not Match'));

            }else{
                $this->Flash->success(__('Your User ID:'.$result['id']));
            }

            
        }
        

    }
    function forgotpassword(){
        $this->request->allowMethod(['get', 'post']);
        $session=$this->request->getSession();        

        $this->viewBuilder()->setLayout('landing');
        $result=null;
        // regardless of POST or GET, redirect if user is logged in
        
        if ($this->request->is('post')) {
           
            $username=$this->request->getData('username');
            $type=$this->request->getData('type');

            if($type=='M'){
                $this->loadModel('Members');
                $result=$this->Members->find()->where(['id'=>$username])->first();
            }
            else
            if($type=='F')
            {
                $this->loadModel('Frenchies');
                $result=$this->Frenchies->find()->where(['id'=>$username])->first();
            }

            if(empty($result)){
                $this->Flash->error(__('Invalid User Name'));

            }else{

                $otp=1234;
                $session->write('username',$username);
                $session->write('type',$type);
                $session->write('otp',$otp);
                $session->write('mobile',$result->mobile);

                //$this->forgotpassword1($type,$username,$result->mobile,$otp);
                $this->redirect('/setpassword');
                
            }

        }
    }

    function setpassword(){
        $this->request->allowMethod(['get', 'post']);

        $session = $this->request->getSession();
        $username= $session->read('username');
        $type= $session->read('type');
        $otp=$session->read('otp');
        $mobile=$session->read('mobile');

        

        $this->viewBuilder()->setLayout('landing');
        $result=null;
        // regardless of POST or GET, redirect if user is logged in
        $error=false;
        if ($this->request->is('post')) {


            $v_otp=$this->request->getData('otp');
            $password=$this->request->getData('password');
            $repassword=$this->request->getData('repassword');

            if($password!=$repassword && !$error) {
                    $this->Flash->error(__('New Password not match with confirm Password'));
                    $error=true;
                }

            if($v_otp!=$otp && !$error){
                $this->Flash->error(__('Invalid OTP,Please try again'));
                $error=true;
            }

            if(!$error){
                
                $user=$this->Users->find()->where(['username'=>$username])->first();

                $user['password']=$password;

                if($this->Users->save($user)){
        $session->delete('username');
        $session->delete('type');
        $session->delete('otp');
        $session->delete('mobile');

                $this->Flash->success(__('Password changed successfuly'));
                //$this->redirect(['controller'=>'pages','action'=>'confirm']);
                $this->redirect('confirm');
                }else
                {
                    $this->Flash->error(__('Some Error occured,Please try again'));
                }

            }

            
        }else{
            
            
 
        }

        $mobile=str_repeat("*" ,strlen($mobile)-4) . substr($mobile,-4);
        $this->set(compact('username','type','mobile'));

        }
        public function countLeaf($id){
            if($id==null)
                return 0;
           
           $this->loadModel('Members');
           $result=$this->Members->find()->select(['id','leftid','rightid'])->where(['id'=>$id])->first();
           
           if($result->leftid==null && $result->rightid==null)
           return 1;
           
           $ret= ($this->countLeaf($result->leftid) + $this->countLeaf($result->rightid));

           return $ret;
                       
        }
}