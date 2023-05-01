<?php declare(strict_types=1);
namespace openvk\Web\Models\Repositories;
use openvk\Web\Models\Entities\User;
use openvk\Web\Models\Repositories\Aliases;
use Nette\Database\Table\ActiveRow;
use Chandler\Database\DatabaseConnection;
use Chandler\Security\User as ChandlerUser;

class Users
{
    private $context;
    private $users;
    private $aliases;
    
    function __construct()
    {
        $this->context = DatabaseConnection::i()->getContext();
        $this->users   = $this->context->table("profiles");
        $this->aliases = $this->context->table("aliases");
    }
    
    private function toUser(?ActiveRow $ar): ?User
    {
        return is_null($ar) ? NULL : new User($ar);
    }
    
    function get(int $id): ?User
    {
        return $this->toUser($this->users->get($id));
    }
    
    function getByShortURL(string $url): ?User
    {
        $shortcode = $this->toUser($this->users->where("shortcode", $url)->fetch());

        if ($shortcode)
            return $shortcode;

        $alias = (new Aliases)->getByShortcode($url);

        if (!$alias) return NULL;
        if ($alias->getType() !== "user") return NULL;
        
        return $alias->getUser();
    }
    
    function getByChandlerUser(ChandlerUser $user): ?User
    {
        return $this->toUser($this->users->where("user", $user->getId())->fetch());
    }
    function find(string $query, string $sort, array $options): Util\EntityStream
    {
        $query  = "%$query%";
        $nnArr = [];
        #$nnOptionsName = [];
        $nnOptionsValue = [];
        foreach($options as $optionName=>$option)
        {
            $option != NULL ? $nnArr+=["$optionName"=>"$option"] : NULL;
            $option != NULL ? array_push($nnOptionsValue, "$option") : NULL;
        }
        $result = $this->users->where("CONCAT_WS(' ', first_name, last_name, pseudo, shortcode) LIKE ?", $query)->where("deleted", 0);
        $nnLength = count($nnOptionsValue);
        if(!is_null($nnArr["male"]) && !is_null($nnArr["female"]) && $nnArr["male"] = $nnArr["female"])
        {
            unset($nnArr["male"]);
            unset($nnArr["female"]);
        }
        #Поговнокодим?
        if($nnLength == 0)
        {
            return new Util\EntityStream("User", $result->order("$sort"));
        }
        else
        {
            if(!is_null($nnArr["hometown"]))
            {
                $result->where("hometown LIKE ?", $nnArr["hometown"]);
            }
            if(!is_null($nnArr["male"]) && $nnArr["male"] == 1)
            {
                $result->where("sex LIKE ?", 0);
            }
            if(!is_null($nnArr["female"]) && $nnArr["female"] == 1)
            {
                $result->where("sex LIKE ?", 1);
            }
            if(!is_null($nnArr["maritalstatus"]))
            {
                $result->where("marital_status LIKE ?", $nnArr["maritalstatus"]);
            }
            if(!is_null($nnArr["status"]))
            {
                $result->where("status LIKE ?", $nnArr["status"]);
            }
            if(!is_null($nnArr["politViews"]))
            {
                $result->where("polit_views LIKE ?", $nnArr["politViews"]);
            }
            if(!is_null($nnArr["email"]))
            {
                $result->where("email_contact LIKE ?", $nnArr["email"]);
            }
            if(!is_null($nnArr["telegram"]))
            {
                $result->where("telegram LIKE ?", $nnArr["telegram"]);
            }
            if(!is_null($nnArr["site"]))
            {
                $result->where("website LIKE ?", $nnArr["site"]);
            }
            if(!is_null($nnArr["address"]))
            {
                $result->where("address LIKE ?", $nnArr["address"]);
            }
            /*elseif(!is_null($nnArr["now_on_site"]))
            {
                $result = $result->where("CONCAT_WS(' ', online) LIKE ?", t);
            }*/
            return new Util\EntityStream("User", $result->order("$sort"));
        }
        
    }
    
    function getStatistics(): object
    {
        return (object) [
            "all"    => sizeof(clone $this->users),
            "active" => sizeof((clone $this->users)->where("online > 0")),
            "online" => sizeof((clone $this->users)->where("online >= ?", time() - 900)),
        ];
    }

    function getByAddress(string $address): ?User
    {
        if(substr_compare($address, "/", -1) === 0)
            $address = substr($address, 0, iconv_strlen($address) - 1);

        $serverUrl = ovk_scheme(true) . $_SERVER["SERVER_NAME"];
        if(strpos($address, $serverUrl . "/") === 0)
            $address = substr($address, iconv_strlen($serverUrl) + 1);

        if(strpos($address, "id") === 0) {
            $user = $this->get((int) substr($address, 2));
            if($user) return $user;
        }

        return $this->getByShortUrl($address);
    }

    /**
     * If you need to check if the user is an instance administrator, use `$user->getChandlerUser()->can("access")->model("admin")->whichBelongsTo(NULL)`.
     * This method is more suitable for instance administrators lists
     */
    function getInstanceAdmins(bool $excludeHidden = true): \Traversable
    {
        $query = "SELECT DISTINCT(`profiles`.`id`) FROM `ChandlerACLRelations` JOIN `profiles` ON `ChandlerACLRelations`.`user` = `profiles`.`user` COLLATE utf8mb4_unicode_520_ci WHERE `ChandlerACLRelations`.`group` IN (SELECT `group` FROM `ChandlerACLGroupsPermissions` WHERE `model` = \"admin\" AND `permission` = \"access\")";

        if($excludeHidden)
        $query .= " AND `ChandlerACLRelations`.`user` NOT IN (SELECT `user` FROM `ChandlerACLRelations` WHERE `group` IN (SELECT `group` FROM `ChandlerACLGroupsPermissions` WHERE `model` = \"hidden_admin\" AND `permission` = \"be\"))";

        $query .= " ORDER BY `profiles`.`id`;";

        $result = DatabaseConnection::i()->getConnection()->query($query);
        foreach($result as $entry)
            yield $this->get($entry->id);
    }
    
    use \Nette\SmartObject;
}
