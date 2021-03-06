<?php

namespace App\Http\Controllers\Character;

use App\Character;
use App\EckPrince\AllFunctions;
use App\EckPrince\Constains;
use App\EckPrince\SystemConfig;
use App\Memb_Info;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Validator;

class CharacterController extends Controller
{
    private $dependence;

    public function __construct(AllFunctions $functions)
    {
        $this->dependence = $functions;
    }

    public function getInfoCharacter(Request $request)
    {
        $listChar = Character::selectRaw('
                               [Name]
                              ,[cLevel]
                              ,[LevelUpPoint]
                              ,[Point_Reserve]
                              ,[Class]
                              ,[Experience]
                              ,[Strength]
                              ,[Dexterity]
                              ,[Vitality]
                              ,[Energy]
                              ,[Leadership]
                              ,[Money]
                              ,[MapNumber]
                              ,[MapPosX]
                              ,[MapPosY]
                              ,[MapDir]
                              ,[PkCount]
                              ,[PkLevel]
                              ,[PkTime]
                              ,[Resets]
                              ,[Relifes]
                              ,[SCFMasterLevel]
                              ,[SCFMasterPoints]
                              ,[SCFMarried]
                              ,[SCFMarryHusbandWife]
                              ,[Lock_Item]
                              ,[SCFSealItem]
                              ,[SCFSealTime]
                              ,[MDate]
                              ,[Top_0h]
                              ')
            ->where('AccountID', $request->account)
            ->orderBy('Relifes', 'desc')
            ->orderBy('Resets', 'desc')->get()->toArray();

        $status_online = 0;
        $check_online = DB::table('MEMB_STAT')->select('ConnectStat')->where('memb___id', $request->account)->first();
        if (count($check_online) > 0) {
            $status_online = $check_online->ConnectStat;
        }

        $list_ghrs = DB::table('BK_Config_Limit_Reset')->get();
        $check_select_char = DB::table('AccountCharacter')->select('GameIDC')->where('Id', $request->account)->first();
        $char_top_1 = Character::selectRaw('[Name],[cLevel],[Resets],[Relifes],[Top_0h]')->where('Top_0h', 1)->first();
        $list_relife = DB::table('BK_Config_Relife')->get();
        $data = array();
        foreach ($listChar as $char) {
            $char['online'] = $status_online;

            if ($char['Name'] == $check_select_char->GameIDC) {
                $char['doinv'] = 0;
            } else {
                $char['doinv'] = 1;
            }
            $char['SCFSealTime'] = date('d/m/Y H:i:s', $char['SCFSealTime']);
            $char['Reset_Day'] = $this->dependence->get_reset_day($request->account, $char['Name']);
            $char['Reset_Month'] = $this->dependence->get_reset_month($request->account, $char['Name']);
            $char['Reset_Limit'] = $this->dependence->calculateLimitReset($char, $list_ghrs, $list_relife, $char_top_1);
            array_push($data, $char);
        }

        $apiFormat = array();
        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = 'Lấy thông tin nhân vật thành công!';
        $apiFormat['data'] = $data;

        return response()->json($apiFormat);
    }

    public function deleteInventory(Request $request)
    {
        $apiFormat = array();

        if ($this->dependence->check_pass2($request->account, $request->pass2) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Mật khẩu cấp 2 không đúng!';
            return response()->json($apiFormat);
        }

        $inventory_query = Character::selectRaw('Inventory')->where('AccountID', $request->account)
            ->where('Name', $request->name)->first();

        $inventory = $inventory_query->Inventory;
        $inventory_fresh = "";

        for ($i = 0; $i < strlen($inventory); $i++) {
            $inventory_fresh .= "F";
        }

        DB::update('update Character set Inventory = 0x' . $inventory_fresh . ' where Name = ?', [$request->name]);

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = 'Xóa đồ nhân vật thành công';

        return response()->json($apiFormat);
    }

    public function resetSkillMaster(Request $request)
    {
        $apiFormat = array();

        if ($this->dependence->check_pass2($request->account, $request->pass2) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Mật khẩu cấp 2 không đúng!';
            return response()->json($apiFormat);
        }

        if ($this->dependence->check_bank_sliver_and_sliver_lock($request->account, SystemConfig::RESET_SKILL_MASTER_SLIVER) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Không đủ Bạc!';
            return response()->json($apiFormat);
        }

        if ($this->dependence->check_bank_zen($request->account, SystemConfig::RESET_SKILL_MASTER_ZEN / 1000000) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Ngân hàng không đủ ZEN!';
            return response()->json($apiFormat);
        }

        $char = Character::select('Class', 'SCFMasterLevel')->where('Name', $request->name)->first();

        $arr_class_master = array(2, 3, 18, 19, 34, 35, 49, 50, 65, 66, 82, 83, 97, 98);

        if (!in_array($char->Class, $arr_class_master, false)) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Nhân vật không phải là nhân vật Master!';
            return response()->json($apiFormat);
        } else {
            $user = Memb_Info::select('bank_sliver', 'bank_sliver_lock', 'bank_zen')->where('memb___id', $request->account)->first();

            $sliver_after = $user->bank_sliver;
            $bank_zen_after = $user->bank_zen - (SystemConfig::RESET_SKILL_MASTER_ZEN / 1000000);

            if ($user->bank_sliver_lock >= SystemConfig::RESET_SKILL_MASTER_SLIVER) {
                $sliver_lock_after = $user->bank_sliver_lock - SystemConfig::RESET_SKILL_MASTER_SLIVER;
            } else {
                $sliver_after = $user->bank_sliver - (SystemConfig::RESET_SKILL_MASTER_SLIVER - $user->bank_sliver_lock);
                $sliver_lock_after = 0;
            }

            $point_master_total = $char->SCFMasterLevel;

            DB::update('UPDATE Character SET SCFMasterPoints=?, SCFMasterSkill=CONVERT(varbinary(180), null) WHERE Name=?', [$point_master_total, $request->name]);

            DB::update('UPDATE MEMB_INFO SET bank_sliver=?, bank_sliver_lock=?, bank_zen=? WHERE memb___id=?', [$sliver_after, $sliver_lock_after, $bank_zen_after, $request->account]);

            $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
            $apiFormat['message'] = $request->name . ' Reset Skill Master thành công';

            return response()->json($apiFormat);
        }
    }

    public function addPoint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'strength' => 'numeric',
            'dexterity' => 'numeric',
            'vitality' => 'numeric',
            'energy' => 'numeric',
            'leadership' => 'numeric',
        ],
            [
                'name.required' => 'Tên nhân vật không được rỗng',
            ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = $message;
            return response()->json($apiFormat);
        }

        $strength = abs(intval($request->strength));
        $dexterity = abs(intval($request->dexterity));
        $vitality = abs(intval($request->vitality));
        $energy = abs(intval($request->energy));
        $leadership = abs(intval($request->leadership));

        $row = Character::select('Strength', 'Dexterity', 'Vitality', 'Energy', 'Leadership', 'LevelUpPoint', 'Class')->WHERE('Name', $request->name)->first();

        $strength_get = $row->Strength;
        $dexterity_get = $row->Dexterity;
        $vitality_get = $row->Vitality;
        $energy_get = $row->Energy;
        $leadership_get = $row->Leadership;
        $level_up_point = $row->LevelUpPoint;

        if ($strength_get < 0) {
            $strength_get = $strength_get + 65536;
        }
        if ($dexterity_get < 0) {
            $dexterity_get = $dexterity_get + 65536;
        }
        if ($vitality_get < 0) {
            $vitality_get = $vitality_get + 65536;
        }
        if ($energy_get < 0) {
            $energy_get = $energy_get + 65536;
        }
        if ($leadership_get < 0) {
            $leadership_get = $leadership_get + 65536;
        }

        $new_str = $strength_get + $strength;
        $new_agi = $dexterity_get + $dexterity;
        $new_vit = $vitality_get + $vitality;
        $new_eng = $energy_get + $energy;
        $new_cmd = $leadership_get + $leadership;

        $total_point_add = $vitality + $strength + $energy + $dexterity + $leadership;

        $point_over = $level_up_point - $total_point_add;


        if ($point_over < 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Bạn chỉ có ' . $level_up_point . ' điểm chưa cộng. Tổng điểm muốn cộng : ' . $total_point_add . ' vượt quá số điểm chưa cộng [' . $point_over . '] điểm.';
            return response()->json($apiFormat);
        }

        DB::update("UPDATE Character SET Strength= ?,Dexterity = ?,Vitality= ?, Energy= ?,Leadership= ?, LevelUpPoint= ? WHERE Name = ? AND AccountID = ?",
            [$new_str, $new_agi, $new_vit, $new_eng, $new_cmd, $point_over, $request->name, $request->account]);

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = $request->name . ' đã cộng điểm thành công. Còn thừa ' . $point_over . ' điểm';
        return response()->json($apiFormat);
    }

    public function resetPoint(Request $request)
    {
        $apiFormat = array();

        if ($this->dependence->check_pass2($request->account, $request->pass2) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Mật khẩu cấp 2 không đúng!';
            return response()->json($apiFormat);
        }

        $char = Character::select('LevelUpPoint', 'Strength', 'Dexterity', 'Vitality', 'Energy', 'Point_Reserve', 'Class')
            ->where('Name', $request->name)->first();

        $LevelUpPoint = $char->LevelUpPoint;
        $Strength = $char->Strength;
        $Dexterity = $char->Dexterity;
        $Vitality = $char->Vitality;
        $Energy = $char->Energy;

        if ($Strength < 0) {
            $Strength = $Strength + 65536;
        }
        if ($Dexterity < 0) {
            $Dexterity = $Dexterity + 65536;
        }
        if ($Vitality < 0) {
            $Vitality = $Vitality + 65536;
        }
        if ($Energy < 0) {
            $Energy = $Energy + 65536;
        }

        $point_reserve = $char->Point_Reserve;
        $ClassType = $char->Class;

        switch ($ClassType) {
            case 0:
            case 1:
            case 2:
            case 3:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 0)->first();
                break;

            case 16:
            case 17:
            case 18:
            case 19:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 16)->first();
                break;

            case 32:
            case 33:
            case 34:
            case 35:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 32)->first();
                break;

            case 48:
            case 49:
            case 50:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 48)->first();
                break;

            case 64:
            case 65:
            case 66:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 64)->first();
                break;

            case 80:
            case 81:
            case 82:
            case 83:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 80)->first();
                break;

            case 96:
            case 97:
            case 98:
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 96)->first();
                break;

            default :
                $point_default = DB::table('DefaultClassType')->select('Strength', 'Dexterity', 'Vitality', 'Energy')->where('Class', 0)->first();
        }

        $Strength_Default = $point_default->Strength;
        $Dexterity_Default = $point_default->Dexterity;
        $Vitality_Default = $point_default->Vitality;
        $Energy_Default = $point_default->Energy;

        $LevelUpPoint = $LevelUpPoint + ($Strength + $Dexterity + $Vitality + $Energy) - ($Strength_Default + $Dexterity_Default + $Vitality_Default + $Energy_Default);

        $Strength = $Strength_Default;
        $Dexterity = $Dexterity_Default;
        $Vitality = $Vitality_Default;
        $Energy = $Energy_Default;

        if ($LevelUpPoint < 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Point đã cộng vào các chỉ số quá ít không đủ để Reset Point';
            return response()->json($apiFormat);
        }

//        if ($LevelUpPoint > 65000) {
//            $point_up = 65000;
//            $point_reserve = $point_reserve + ($LevelUpPoint - 65000);
//        } else {
        $point_up = $LevelUpPoint;
//        }

        DB::update("UPDATE Character SET LevelUpPoint = ?, Strength = ?, Dexterity = ?, Vitality = ?, Energy = ?, point_reserve=? WHERE Name=?",
            [$point_up, $Strength, $Dexterity, $Vitality, $Energy, $point_reserve, $request->name]);

        $result['LevelUpPoint'] = $point_up;
        $result['pointdutru'] = $point_reserve;

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = "Tẩy điểm cho nhân vât " . $request->name . ' thành công!!';
        $apiFormat['data'] = $result;
        return response()->json($apiFormat);
    }

    public function moveLorencia(Request $request)
    {

        DB::update("UPDATE Character SET MapNumber=0, MapPosX=143, MapPosY = 134, MapDir = 0 WHERE Name = ?", [$request->name]);

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = "$request->name đã di chuyển đến Lorencia thành công!";
        return response()->json($apiFormat);
    }

    public function lockItem(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'keyLock' => 'required',
            'reKeyLock' => 'required|same:keyLock',
        ],
            [
                'name.required' => 'Tên nhân vật không được rỗng',
                'keyLock.required' => 'Chưa nhập mã khóa đồ',
                'reKeyLock.required' => 'Chưa nhập mã khóa đồ',
                'reKeyLock.same' => 'Mã nhập lại không khớp',
            ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = $message;
            return response()->json($apiFormat);
        }

        $query = Character::select('name', 'lock_item', 'lock_item_code', 'CtlCode', 'ErrorSubBlock')->WHERE('Name', $request->name)->first();

        if ($request->CtlCode == 1) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = "$request->name bị block không thể sử dụng chức năng này!";
            return response()->json($apiFormat);
        }

        $is_lock_item = $query->lock_item;

        if ($request->actionType == 0) {

            if ($is_lock_item == 1) {
                $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
                $apiFormat['message'] = "$request->name đã khóa đồ!";
                return response()->json($apiFormat);
            } else {
                DB::update("UPDATE Character SET lock_item=1, lock_item_code=?, CtlCode = 18 WHERE Name = ? AND AccountID = ?", [$request->keyLock, $request->name, $request->account]);
                $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
                $apiFormat['message'] = "$request->name khóa đồ thành công!";
                return response()->json($apiFormat);
            }
        } else {
            if ($is_lock_item == 0) {
                $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
                $apiFormat['message'] = "$request->name không khóa đồ!";
                return response()->json($apiFormat);
            } else {
                if (strcmp($request->keyLock, $query->lock_item_code) != 0) {
                    $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
                    $apiFormat['message'] = "$request->name mã khóa không đúng!";
                    return response()->json($apiFormat);
                }

                DB::update("UPDATE Character SET lock_item=0, lock_item_code=NULL , CtlCode =0 WHERE Name = ? AND AccountID = ?", [$request->name, $request->account]);
                $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
                $apiFormat['message'] = "$request->name mở khóa đồ thành công!";
                return response()->json($apiFormat);
            }
        }
    }

    public function clearPK(Request $request)
    {
        $apiFormat = array();

        $name = $request->name;
        $memb___id = $request->account;

        $char = Character::select('PkLevel', 'PkCount', 'Money')->where('PkCount', '>', 0)->where('Name', $name)->first();

        if (count($char) <= 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Bạn không phải là sát thủ';
            return response()->json($apiFormat);
        }

        if ($char->PkCount <= SystemConfig::CLEAR_PK_COUNT) {
            $zen_need = $char->PkCount * SystemConfig::CLEAR_PK_ZEN;
            $sliver_need = 0;
        } else {
            $zen_need = 0;
            $sliver_need = $char->PkCount * SystemConfig::CLEAR_PK_SLIVER;
        }

        $acc = Memb_Info::select('bank_sliver', 'bank_sliver_lock')->where('memb___id', $memb___id)->first();

        if ($char->Money < $zen_need) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = "Nhân vật của bạn không đủ ZEN. Cần " . number_format($zen_need, 0, ",", ".") . " ZEN";
            return response()->json($apiFormat);
        }

        if (($acc->bank_sliver + $acc->bank_sliver_lock) < $sliver_need) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = "Bạn không đủ Bạc. Cần " . number_format($sliver_need, 0, ",", ".") . " Bạc";
            return response()->json($apiFormat);
        }

        $money_after = $char->Money - $zen_need;

        if ($zen_need > 0) {
            DB::update("UPDATE Character SET PkLevel = 3, PkTime = 0, PkCount = 0, Money = ? WHERE AccountID = ? AND Name = ?", [$money_after, $memb___id, $name]);
        }

        if ($sliver_need > 0) {
            DB::update("UPDATE Character SET PkLevel = 3, PkTime = 0, PkCount = 0 WHERE AccountID = ? AND Name = ?", [$memb___id, $name]);

            if ($acc->bank_sliver_lock > $sliver_need) {
                DB::update("UPDATE MEMB_INFO SET bank_sliver_lock = ? WHERE memb___id = ?", [$acc->bank_sliver_lock - $sliver_need, $memb___id]);
            } else {
                DB::update("UPDATE MEMB_INFO SET bank_sliver = ?, bank_sliver_lock = 0 WHERE memb___id = ?", [$acc->bank_sliver - ($sliver_need - $acc->bank_sliver_lock), $memb___id]);
            }
        }

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = $request->name . ' rửa tội thành công';
        return response()->json($apiFormat);

    }

    public function changeClass(Request $request)
    {
        $apiFormat = array();

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'pass2' => 'required',
            'classType' => 'required|numeric',
        ],
            [
                'name.required' => 'Tên nhân vật không được rỗng',
                'pass2.required' => 'Vui lòng điền mật khẩu cấp 2',
                'classType.required' => 'Chưa chọn giới tính cần đổi'
            ]);

        if ($validator->fails()) {
            $message = $validator->errors()->first();
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = $message;
            return response()->json($apiFormat);
        }

        if ($this->dependence->check_pass2($request->account, $request->pass2) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Mật khẩu cấp 2 không đúng!';
            return response()->json($apiFormat);
        }

        if ($this->dependence->check_sliver($request->account, SystemConfig::CHANGE_CLASS_SLIVER) === 0) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Không đủ Bạc!';
            return response()->json($apiFormat);
        }

        $char_info = Character::select('Name', 'cLevel', 'Class', 'Resets', 'Relifes', 'Top_0h')
            ->where('AccountID', $request->account)
            ->where('Name', $request->name)
            ->first();

        $class_default = $this->dependence->getClassDefault($char_info->Class);
        if ($class_default == (int)$request->classType) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Giới tính muốn đổi trùng với giới tính hiện tại.';
            return response()->json($apiFormat);
        }

        $inventory_query = Character::selectRaw('Inventory')->where('AccountID', $request->account)
            ->where('Name', $request->name)->first();

        $inventory = $inventory_query->Inventory;
//        $inventory = bin2hex($inventory);
        $inventory = strtoupper($inventory);
        $inventory1 = substr($inventory, 0, 12 * 32);

        $inventory1_fresh = "";
        for ($i = 0; $i < strlen($inventory1); $i++) {
            $inventory1_fresh .= "F";
        }

        if ($inventory1 != $inventory1_fresh) {
            $apiFormat['status'] = Constains::RESPONSE_STATUS_ERROR;
            $apiFormat['message'] = 'Bạn chưa cất hết đồ trên người nhân vật';
            return response()->json($apiFormat);
        }

        $no_quest = "FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFF";

        DB::update("UPDATE Character SET Class=?, MagicList=CONVERT(varbinary(180), null), Quest=0x" . $no_quest . ", Change_Class_Time=? WHERE Name = ?", [
            $request->classType, time(), $request->name]);
        DB::update("UPDATE MEMB_INFO SET bank_sliver=bank_sliver - ?  WHERE memb___id = ?", [SystemConfig::CHANGE_CLASS_SLIVER, $request->account]);

        $class_before = $this->dependence->getClassName($char_info->Class);
        $class_after = $this->dependence->getClassName($request->classType);

        $apiFormat['status'] = Constains::RESPONSE_STATUS_OK;
        $apiFormat['message'] = "Nhân vật $request->name đã đổi giới tính từ $class_before sang $class_after thành công!";
        return response()->json($apiFormat);
    }


}
