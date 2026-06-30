<?php

namespace Database\Seeders;

use App\Models\MurmurationComment;
use App\Models\MurmurationPost;
use App\Models\MurmurationTopic;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MurmurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = $this->members();
        $topics = $this->topics();

        $posts = collect()
            ->merge(MurmurationPost::factory()->count(70)->recycle($members)->recycle($topics)->create())
            ->merge(MurmurationPost::factory()->count(20)->image()->recycle($members)->recycle($topics)->create())
            ->merge(MurmurationPost::factory()->count(10)->audio()->recycle($members)->recycle($topics)->create());

        $posts->each(fn (MurmurationPost $post) => $this->seedEngagement($post, $members));
    }

    /**
     * Get a pool of members to author posts and comments, topping up if needed.
     *
     * @return Collection<int, User>
     */
    private function members(): Collection
    {
        $members = User::query()->take(12)->get();

        if ($members->count() < 8) {
            $members = $members->merge(
                User::factory()->count(8 - $members->count())->create()
            );
        }

        return $members;
    }

    /**
     * Create the five community topics.
     *
     * @return Collection<int, MurmurationTopic>
     */
    private function topics(): Collection
    {
        return collect(['Wellbeing', 'Productivity', 'Mindfulness', 'Community', 'Announcements'])
            ->map(fn (string $name): MurmurationTopic => MurmurationTopic::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'status' => true],
            ));
    }

    /**
     * Attach likes, saves, comments and author replies to a post.
     *
     * @param  Collection<int, User>  $members
     */
    private function seedEngagement(MurmurationPost $post, Collection $members): void
    {
        $post->likers()->syncWithoutDetaching($this->randomMemberIds($members, 0, $members->count()));
        $post->savers()->syncWithoutDetaching($this->randomMemberIds($members, 0, 4));

        MurmurationComment::factory()
            ->count(fake()->numberBetween(0, 5))
            ->recycle($members)
            ->for($post, 'post')
            ->create()
            ->each(function (MurmurationComment $comment) use ($post, $members): void {
                // The post author replies to roughly 40% of comments (one reply each).
                if (fake()->boolean(40)) {
                    MurmurationComment::factory()
                        ->replyTo($comment)
                        ->create(['user_id' => $post->user_id]);
                }

                $comment->likers()->syncWithoutDetaching($this->randomMemberIds($members, 0, 5));
            });
    }

    /**
     * Pick a random spread of member ids to attach via a pivot.
     *
     * @param  Collection<int, User>  $members
     * @return array<int, int>
     */
    private function randomMemberIds(Collection $members, int $min, int $max): array
    {
        $count = fake()->numberBetween($min, min($max, $members->count()));

        return $members->random($count)->modelKeys();
    }
}
