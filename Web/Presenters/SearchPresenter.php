<?php declare(strict_types=1);
namespace openvk\Web\Presenters;
use openvk\Web\Models\Entities\{Video, Post, User, Club, Comment};
use openvk\Web\Models\Repositories\{Videos, Posts, Users, Clubs, Comments};
use Chandler\Database\DatabaseConnection;

final class SearchPresenter extends OpenVKPresenter
{
    private $users;
    private $clubs;
    private $comments;
    private $posts;
    private $videos;

    function __construct(Users $users, Clubs $clubs)
    {
        $this->users = $users;
        $this->clubs = $clubs;
        $this->comments = new Comments;
        $this->posts = new Posts;
        $this->videos = new Videos;
        parent::__construct();
    }
    
    function renderIndex(): void
    {
        $query = $this->queryParam("query") ?? "";
        $type  = $this->queryParam("type") ?? "users";
        $sort = "id";
        $desc = $this->queryParam("desc") == "on" ? "ASC" : "DESC";
        $settings = [
            "type" => $this->queryParam("type"),
            "hometown" => $this->queryParam("city") != "" ? $this->queryParam("city") : NULL,
            "age1" => $this->queryParam("age1"),
            "age2" => $this->queryParam("age2"),
            "male" => $this->queryParam("male") == "on" ? 1 : NULL,
            "female" => $this->queryParam("female") == "on" ? 1 : NULL,
            "maritalstatus" => $this->queryParam("maritalstatus") != 0 ? $this->queryParam("maritalstatus") : NULL,
            "with_photo" => $this->queryParam("with_photo"),
            "now_on_site" => $this->queryParam("now_on_site"),
            "status" => $this->queryParam("status") != "" ? $this->queryParam("status") : NULL,
            "politViews" => $this->queryParam("politViews") != 0 ? $this->queryParam("politViews") : NULL,
            "email" => $this->queryParam("email"),
            "telegram" => $this->queryParam("telegram"),
            "site" => $this->queryParam("site") != "" ? "https://".$this->queryParam("site") : NULL,
            "address" => $this->queryParam("address"),
        ];
        if($settings["age1"] > $settings["age2"])
        {
            $settings["age1"] = $settings["age2"]-$settings["age1"];
        }
        #Можно было бы вообще настройки сортировки из адресной строки сразу получать, но тогда это некрасиво будет выглядеть
        switch($this->queryParam("sort"))
        {
            case "id":
                $sort = "`id` $desc";
                break;
            case "first_name":
                $sort = "`first_name` $desc";
                break;
            case "rating":
                $sort = "`rating` $desc";
                break;
            case "random":
                $sort = "RAND()";
                break;
        }
        $page  = (int) ($this->queryParam("p") ?? 1);
        
        $this->willExecuteWriteAction();
        if($query != "")
            $this->assertUserLoggedIn();
        
        # https://youtu.be/pSAWM5YuXx8
        # https://youtu.be/FpCnoEBahr0
        
        $repos = [ "groups" => "clubs", "users" => "users", "comments" => "comments", "posts" => "posts", "videos" => "videos"];
        $repo  = $repos[$type] or $this->throwError(400, "Bad Request", "Invalid search entity $type.");
        
        $results  = $this->{$repo}->find($query, $sort, $settings);
        $iterator = $results->page($page);
        $count    = $results->size();
        
        $this->template->iterator = iterator_to_array($iterator);
        $this->template->count    = $count;
        $this->template->type     = $type;
        $this->template->page     = $page;
    }
}
