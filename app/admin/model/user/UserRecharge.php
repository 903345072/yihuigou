<?php
/**
 * @author: xaboy<365615158@qq.com>
 * @day: 2017/11/28
 */

namespace app\admin\model\user;

use app\admin\model\system\SystemAdmin;
use crmeb\traits\ModelTrait;
use crmeb\basic\BaseModel;
use crmeb\services\PHPExcelService;

/**
 * 用户充值记录
 * Class UserRecharge
 * @package app\admin\model\user
 */
class UserRecharge extends BaseModel
{

    /**
     * 数据表主键
     * @var string
     */
    protected $pk = 'id';

    /**
     * 模型名称
     * @var string
     */
    protected $name = 'user_recharge';

    use ModelTrait;

    public static function systemPage($where)
    {
        $model = new self;
        $model = $model->alias('A');
        if ($where['order_id'] != '') {
            $model = $model->whereOr('A.order_id', 'like', "%$where[order_id]%");
            $model = $model->whereOr('A.id', (int)$where['order_id']);
            $model = $model->whereOr('B.nickname', 'like', "%$where[order_id]%");
        }
        $model = $model->where('A.recharge_type', 'weixin');
        $model = $model->where('A.paid', 1);
        $model = $model->field('A.*,B.nickname');
        $model = $model->join('user B', 'A.uid = B.uid', 'RIGHT');
        $model = $model->order('A.id desc');

        return self::page($model, $where);
    }

    /*
     * 设置查询条件
     * @param array $where
     * @param object $model
     * */
    public static function setWhere($where, $model = null, $alias = '', $join = '')
    {
        $model = is_null($model) ? new self() : $model;
        if ($alias) {
            $model = $model->alias('a');
            $alias .= '.';
        }
        $admin_info = SystemAdmin::activeAdminInfoOrFail();
        $power = $admin_info['power'];

        if ($power >=9999){
            //根据代理查询经纪人再查询用户
            if (isset($where['operation_id']) && $where['operation_id'] != ''){
                //经纪人id
                $manager_ids =   SystemAdmin::where(['pid'=>$where['operation_id'],'power'=>9997])->column('id');
                //客户id
                $user_ids = \app\models\user\User::where(['pid'=>$manager_ids])->column('uid');
                $model->where("{$alias}uid", "in", $user_ids);
            }
        }


        if (isset($where['manager_id']) && $where['manager_id'] != ''){
            //客户id
            if ($power >= 9999){  //如果是总后台搜索
                $user_ids = \app\models\user\User::where(['pid'=>$where['manager_id']])->column('uid');
                $model->where($alias . 'uid', "in", $user_ids);
            }else if($power == 9998){  //如果是代理搜索,只能搜索下属经纪人
                //找到下属经纪人
                $chil_manager =  SystemAdmin::where(['pid'=>$admin_info['id'],'power'=>9997])->column('id');
                if (in_array($where['manager_id'],$chil_manager)){
                    $user_ids = \app\models\user\User::where(['pid'=>$where['manager_id']])->column('uid');
                    $model->where($alias . 'uid', "in", $user_ids);
                }else{

                    $model->where($alias . 'uid', "in", [-1]);
                }
            }

        }else{
            //代理和经纪人只能看自己的客户订单
            if ($power < 9999){

                //代理
                if ($power == 9998){
                    $manager_ids = SystemAdmin::where(['pid'=>$admin_info['id'],'power'=>9997])->column('id');
                    //客户id
                    $user_ids = \app\models\user\User::where(['pid'=>$manager_ids])->column('uid');
                    $model->where($alias . 'uid', "in", $user_ids);
                }
                //经纪人
                if ($power == 9997)
                {
                    $user_ids = \app\models\user\User::where(['pid'=>$admin_info['id']])->column('uid');
                    $model->where($alias . 'uid', "in", $user_ids);
                }

            }
        }
        if ($where['data']) $model = self::getModelTime($where, $model, "{$alias}add_time");
        if ($where['paid'] != '') $model = $model->where("{$alias}paid", $where['paid']);
        if ($where['nickname']) $model = $model->where("{$alias}uid|{$alias}order_id" . ($join ? '|' . $join : ''), 'LIKE', "%$where[nickname]%");
        return $model->order("{$alias}add_time desc");
    }

    /*
     * 查找用户充值金额记录
     * @param array $where
     * @return array
     *
     * */
    public static function getUserRechargeList($where)
    {
        $model = self::setWhere($where, null, 'a', 'u.nickname')->join('user u', 'u.uid=a.uid')->field(['a.*', 'u.nickname', 'u.avatar']);
        if ($where['excel']) self::saveExcel($model->select()->toArray());
        $data = $model->page($where['page'], $where['limit'])->select();
        $data = count($data) ? $data->toArray() : [];
        foreach ($data as &$item) {
            switch ($item['recharge_type']) {
                case 'routine':
                    $item['_recharge_type'] = '小程序充值';
                    break;
                case 'weixin':
                    $item['_recharge_type'] = '微信充值';
                    break;
                case 'zhifubao':
                    $item['_recharge_type'] = '支付宝充值';
                    break;
                case 'sys':
                    $item['_recharge_type'] = '系统增加余额';
                    break;    
                default:
                    $item['_recharge_type'] = '其他充值';
                    break;
            }
            $item['_pay_time'] = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : date('Y-m-d H:i:s', $item['add_time']);
            $item['_add_time'] = $item['add_time'] ? date('Y-m-d H:i:s', $item['add_time']) : '暂无';
            $item['paid_type'] = $item['paid'] ? '已支付' : '未支付';
        }
        $count = self::setWhere($where, null, 'a', 'u.nickname')->join('user u', 'u.uid=a.uid')->count();
        return compact('data', 'count');
    }

    public static function saveExcel($data)
    {
        $excel = [];
        foreach ($data as $item) {
            switch ($item['recharge_type']) {
                case 'routine':
                    $item['_recharge_type'] = '小程序充值';
                    break;
                case 'weixin':
                    $item['_recharge_type'] = '微信充值';
                    break;
                case 'zhifubao':
                    $item['_recharge_type'] = '支付宝充值';
                    break;    
                default:
                    $item['_recharge_type'] = '其他充值';
                    break;
            }
            $item['_pay_time'] = $item['pay_time'] ? date('Y-m-d H:i:s', $item['pay_time']) : '暂无';
            $item['_add_time'] = $item['add_time'] ? date('Y-m-d H:i:s', $item['add_time']) : '暂无';
            $item['paid_type'] = $item['paid'] ? '已支付' : '未支付';

            $excel[] = [
                $item['nickname'],
                $item['price'],
                $item['paid_type'],
                $item['_recharge_type'],
                $item['_pay_time'],
                $item['paid'] == 1 && $item['refund_price'] == $item['price'] ? '已退款' : '未退款',
                $item['_add_time']
            ];
        }
        PHPExcelService::setExcelHeader(['昵称/姓名', '充值金额', '是否支付', '充值类型', '支付事件', '是否退款', '添加时间'])
            ->setExcelTile('充值记录', '充值记录' . time(), ' 生成时间：' . date('Y-m-d H:i:s', time()))
            ->setExcelContent($excel)
            ->ExcelSave();
    }

    /*
     * 获取用户充值数据
     * @return array
     * */
    public static function getDataList($where)
    {
        return [
            [
                'name' => '充值总金额',
                'field' => '元',
                'count' => self::setWhere($where, null, 'a', 'u.nickname')->where(["paid"=>1])->join('user u', 'u.uid=a.uid')->sum('a.price'),
                'background_color' => 'layui-bg-cyan',
                'col' => 3,
            ],
         
           
            [
                'name' => '微信充值金额',
                'field' => '元',
                'count' => self::setWhere($where, null, 'a', 'u.nickname')->where('a.recharge_type', 'weixin')->join('user u', 'u.uid=a.uid')->sum('a.price'),
                'background_color' => 'layui-bg-cyan',
                'col' => 3,
            ],
            [
                'name' => '支付宝充值金额',
                'field' => '元',
                'count' => self::setWhere($where, null, 'a', 'u.nickname')->where('a.recharge_type', 'zhifubao')->join('user u', 'u.uid=a.uid')->sum('a.price'),
                'background_color' => 'layui-bg-cyan',
                'col' => 3,
            ],
        ];
    }

}