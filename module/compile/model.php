<?php
/**
 * The model file of compile module of ZenTaoCMS.
 *
 * @copyright   Copyright 2009-2015 青岛易软天创网络科技有限公司(QingDao Nature Easy Soft Network Technology Co,LTD, www.cnezsoft.com)
 * @license     ZPL (http://zpl.pub/page/zplv12.html)
 * @author      Yidong Wang <yidong@cnezsoft.com>
 * @package     compile
 * @version     $Id$
 * @link        http://www.zentao.net
 */
class compileModel extends model
{
    /**
     * Get build list.
     * 
     * @param  int    $jobID 
     * @param  string $orderBy 
     * @param  object $pager 
     * @access public
     * @return array
     */
    public function getList($jobID, $orderBy = 'id_desc', $pager = null)
    {
        return $this->dao->select('t1.id, t1.name, t1.status, t1.createdDate, t2.triggerType, t3.name as repoName, t4.name as jenkinsName')->from(TABLE_COMPILE)->alias('t1')
            ->leftJoin(TABLE_INTEGRATION)->alias('t2')->on('t1.cijob=t2.id')
            ->leftJoin(TABLE_REPO)->alias('t3')->on('t2.repo=t3.id')
            ->leftJoin(TABLE_JENKINS)->alias('t4')->on('t2.jenkins=t4.id')
            ->where('t1.deleted')->eq('0')
            ->andWhere('t1.cijob')->eq($jobID)
            ->orderBy($orderBy)
            ->page($pager)
            ->fetchAll('id');
    }

    /**
     * Get by id 
     * 
     * @param  int    $buildID 
     * @access public
     * @return object
     */
    public function getByID($buildID)
    {
        return $this->dao->select('*')->from(TABLE_COMPILE)->where('id')->eq($buildID)->fetch();
    }

    /**
     * Get unexecuted list.
     * 
     * @access public
     * @return array
     */
    public function getUnexecutedList()
    {
        return $this->dao->select('*')->from(TABLE_COMPILE)->where('status')->eq('')->andWhere('deleted')->eq('0')->fetchAll();
    }

    /**
     * Save build by job
     * 
     * @param  object    $job 
     * @access public
     * @return void
     */
    public function createByIntegration($integrationID)
    {
        $integration = $this->dao->select('id,name')->from(TABLE_INTEGRATION)->where('id')->eq($integrationID)->fetch();

        $build = new stdClass();
        $build->cijob       = $integration->id;
        $build->name        = $integration->name;
        $build->createdBy   = $this->app->user->account;
        $build->createdDate = helper::now();

        $this->dao->insert(TABLE_COMPILE)->data($build)->exec();
    }

    /**
     * Execute compile
     * 
     * @param  object $compile 
     * @access public
     * @return bool
     */
    public function execByCompile($compile, $data = null)
    {
        $integration = $this->dao->select('t1.id as jobId,t1.name as jobName,t1.repo,t1.jenkinsJob,t2.name as jenkinsName,t2.serviceUrl,t2.account,t2.token,t2.password')
            ->from(TABLE_INTEGRATION)->alias('t1')
            ->leftJoin(TABLE_JENKINS)->alias('t2')->on('t1.jenkins=t2.id')
            ->where('t1.id')->eq($compile->cijob)
            ->fetch();

        if(!$integration) return false;

        $buildUrl = $this->getBuildUrl($integration);
        $build    = new stdclass();
        $build->queueItem = $this->loadModel('ci')->sendRequest($buildUrl, $data);
        $build->status    = $build->queueItem ? 'created' : 'create_fail';
        $this->dao->update(TABLE_COMPILE)->data($build)->where('id')->eq($compile->id)->exec();

        return !dao::isError();
    }

    /**
     * Execute by integration.
     * 
     * @param  int $compile 
     * @access public
     * @return bool
     */
    public function execByIntegration($integrationID, $data = null)
    {
        $integration = $this->dao->select('t1.id as jobId,t1.name as jobName,t1.repo,t1.jenkinsJob,t2.name as jenkinsName,t2.serviceUrl,t2.account,t2.token,t2.password')
            ->from(TABLE_INTEGRATION)->alias('t1')
            ->leftJoin(TABLE_JENKINS)->alias('t2')->on('t1.jenkins=t2.id')
            ->where('t1.id')->eq($integrationID)
            ->fetch();

        if(!$integration) return false;

        $buildUrl = $this->getBuildUrl($integration);
        $build    = new stdClass();
        $build->cijob       = $integration->jobId;
        $build->name        = $integration->jobName;
        $build->queueItem   = $this->loadModel('ci')->sendRequest($buildUrl, $data);
        $build->status      = $build->queueItem ? 'created' : 'create_fail';
        $build->createdBy   = $this->app->user->account;
        $build->createdDate = helper::now();
        $this->dao->insert(TABLE_COMPILE)->data($build)->exec();

        return !dao::isError();
    }

    /**
     * Get build url.
     * 
     * @param  object $jenkins 
     * @access public
     * @return string
     */
    public function getBuildUrl($jenkins)
    {
        $jenkinsServer   = $jenkins->serviceUrl;
        $jenkinsUser     = $jenkins->account;
        $jenkinsPassword = $jenkins->token ? $jenkins->token : base64_decode($jenkins->password);

        $jenkinsAuth   = '://' . $jenkinsUser . ':' . $jenkinsPassword . '@';
        $jenkinsServer = str_replace('://', $jenkinsAuth, $jenkinsServer);
        $buildUrl      = sprintf('%s/job/%s/buildWithParameters/api/json', $jenkinsServer, $jenkins->jenkinsJob);
        return $buildUrl;
    }
}
