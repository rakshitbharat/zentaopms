<?php
/**
 * The model file of ci module of ZenTaoPMS.
 * @author      Chenqi <chenqi@cnezsoft.com>
 * @package     product
 * @version     $Id: $
 * @link        http://www.zentao.net
 */

class ciModel extends model
{
    /**
     * Set menu.
     * 
     * @access public
     * @return void
     */
    public function setMenu()
    {
        $repoID = $this->session->repoID;
        $moduleName = $this->app->getModuleName();
        foreach($this->lang->{$moduleName}->menu as $key => $menu) common::setMenuVars($this->lang->{$moduleName}->menu, $key, $repoID);
        $this->lang->{$moduleName}->menuOrder = $this->lang->ci->menuOrder;
    }

    /**
     * Send a request to jenkins to check build status.
     *
     * @access public
     * @return bool
     */
    public function checkBuildStatus()
    {
        $compiles = $this->dao->select('t1.*, t2.jenkinsJob, t3.name jenkinsName,t3.serviceUrl,t3.account,t3.token,t3.password')
            ->from(TABLE_COMPILE)->alias('t1')
            ->leftJoin(TABLE_INTEGRATION)->alias('t2')->on('t1.cijob=t2.id')
            ->leftJoin(TABLE_JENKINS)->alias('t3')->on('t2.jenkins=t3.id')
            ->where('t1.status')->ne('success')
            ->andWhere('t1.status')->ne('fail')
            ->andWhere('t1.status')->ne('timeout')
            ->andWhere('t1.createdDate')->gt(date(DT_DATETIME1, strtotime("-1 day")))
            ->fetchAll();

        foreach($compiles as $compile)
        {
            $jenkinsServer   = $compile->serviceUrl;
            $jenkinsUser     = $compile->account;
            $jenkinsPassword = $compile->token ? $compile->token : base64_decode($compile->password);

            $jenkinsAuth   = '://' . $jenkinsUser . ':' . $jenkinsPassword . '@';
            $jenkinsServer = str_replace('://', $jenkinsAuth, $jenkinsServer);
            $queueUrl      = sprintf('%s/queue/item/%s/api/json', $jenkinsServer, $compile->queueItem);

            $response = common::http($queueUrl);
            if(strripos($response, "404") > -1)
            {
                /* Queue expired, use another api. */
                $infoUrl   = sprintf('%s/job/%s/%s/api/json', $jenkinsServer, $compile->jenkinsJob, $compile->queueItem);
                $response  = common::http($infoUrl);
                $buildInfo = json_decode($response);
                $result    = strtolower($buildInfo->result);
                $this->updateBuildStatus($compile, $result);

                $logUrl   = sprintf('%s/job/%s/%s/consoleText', $jenkinsServer, $compile->jenkinsJob, $compile->queueItem);
                $response = common::http($logUrl);
                $logs     = json_decode($response);

                $this->dao->update(TABLE_COMPILE)->set('logs')->eq($response)->where('id')->eq($compile->id)->exec();
            }
            else
            {
                $queueInfo = json_decode($response);
                if(!empty($queueInfo->executable))
                {
                    $buildUrl = $queueInfo->executable->url . 'api/json?pretty=true';
                    $buildUrl = str_replace('://', $jenkinsAuth, $buildUrl);

                    $response  = common::http($buildUrl);
                    $buildInfo = json_decode($response);

                    if($buildInfo->building)
                    {
                        $this->updateBuildStatus($compile, 'building');
                    }
                    else
                    {
                        $result = strtolower($buildInfo->result);
                        $this->updateBuildStatus($compile, $result);

                        $logUrl = $buildInfo->url . 'logText/progressiveText/api/json';
                        $logUrl = str_replace('://', $jenkinsAuth, $logUrl);

                        $response = common::http($logUrl);
                        $logs     = json_decode($response);

                        $this->dao->update(TABLE_COMPILE)->set('logs')->eq($response)->where('id')->eq($compile->id)->exec();
                    }
                }
            }
        }
    }

    /**
     * Update ci build status.
     *
     * @param  object $build
     * @param  string $status
     * @access public
     * @return bool
     */
    public function updateBuildStatus($build, $status)
    {
        $this->dao->update(TABLE_COMPILE)->set('status')->eq($status)->where('id')->eq($build->id)->exec();
        $this->dao->update(TABLE_INTEGRATION)->set('lastExec')->eq(helper::now())->set('lastStatus')->eq($status)->where('id')->eq($build->cijob)->exec();
    }

    /**
     * @param $url
     * @return false|mixed|string
     */
    public function sendRequest($url, $data)
    {
        if(!empty($data->PARAM_TAG)) $data->PARAM_REVISION = '';

        $response = common::http($url, $data, true);
        if(preg_match("!Location: .*item/(.*)/!", $response, $matches)) return $matches[1];
        return 0;
    }
}
