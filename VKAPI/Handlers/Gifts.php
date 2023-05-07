<?php declare(strict_types=1);
namespace openvk\VKAPI\Handlers;
use openvk\Web\Models\Repositories\Users as UsersRepo;
use openvk\Web\Models\Repositories\Gifts as GiftsRepo;
use openvk\Web\Models\Entities\Notifications\GiftNotification;

final class Gifts extends VKAPIRequestHandler
{
    function get(int $user_id, int $count = 100, int $offset = 0)
    {
        $this->requireUser();

        $i = 0;
        $i+=$offset;
        $user = (new UsersRepo)->get($user_id);
        $gift_item = array();
        $userGifts = $user->getGifts(1, $count, false);
        if(sizeof($userGifts) < 0)
        {
            return NULL;
        }
        foreach($userGifts as $gift)
        {
            if($i < $count)
            {
            $gift_item[] = array(
                "id"        => $i,
                "from_id"   => $gift->anon == true ? 0 : $gift->sender->getId(),
                "message"   => $gift->caption == NULL ? "" : $gift->caption,
                "date"      => $gift->sent->timestamp(),
                "gift"      => array(
                    "id"          => $gift->gift->getId(),
                    "thumb_256"   => $gift->gift->getImage(2),
                    "thumb_96"    => $gift->gift->getImage(2),
                    "thumb_48"    => $gift->gift->getImage(2)
                ),
                "privacy"   => 0 //zaglooshka
                );
            }
            $i+=1;
        }
        return $gift_item;
    }
    function send(int $user_ids, int $gift_id, string $message = "", int $privacy = 0)
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        $user = (new UsersRepo)->get((int) $user_ids);
        $gift = (new GiftsRepo)->get($gift_id);
        $price = $gift->getPrice();
        $coinsLeft = $this->getUser()->getCoins() - $price;

        if(!$gift->canUse($this->getUser()))
            return (object)
            [
                "success"         => 0,
                "user_ids"        => $user_ids,
                "error"           => "You don't have any more of these gifts."
            ];
        
        if($coinsLeft < 0)
            return (object)
            [
                "success"         => 0,
                "user_ids"        => $user_ids,
                "error"           => "You don't have enough kromer."
            ];
        
        $user->gift($this->getUser(), $gift, $message);
        $gift->used();
        $this->getUser()->setCoins($coinsLeft);
        $this->getUser()->save();

        $notification = new GiftNotification($user, $this->getUser(), $gift, $message);
        $notification->emit();
        return (object)
        [
            "success"         => 1,
            "user_ids"        => $user_ids,
            "withdraw_votes"  => $price
        ];
    }
    function delete()
    {
        $this->requireUser();
        $this->willExecuteWriteAction();

        return 0;
    }
}