<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Markets\Models\Market;
use App\Domains\Templates\Enums\StageType;
use App\Domains\Templates\Models\Template;
use App\Domains\Templates\Models\TemplateSection;
use App\Domains\Users\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Template>
     */
    protected $model = Template::class;

    protected bool $seedSections = true;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_id' => Market::factory(),
            'name' => fake()->unique()->company().' Template',
            'description' => fake()->sentence(),
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function withoutSections(): static
    {
        $this->seedSections = false;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    protected function newInstance(array $arguments = []): static
    {
        $instance = parent::newInstance($arguments);

        $instance->seedSections = $this->seedSections;
        $instance->afterCreating = $instance->afterCreating->map(
            function ($callback) use ($instance): \Closure {
                if (! $callback instanceof \Closure) {
                    throw new \LogicException('TemplateFactory afterCreating callbacks must be closures.');
                }

                return $callback->bindTo($instance);
            },
        );

        return $instance;
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Template $template): void {
            if (! $this->seedSections) {
                return;
            }

            $headings = [
                StageType::PRE_CONSTRUCTION->value => 'Pre Construction',
                StageType::CONSTRUCTION->value => 'Construction / Installation',
                StageType::POST_COMPLETION->value => 'Post Completion',
            ];

            $sortOrder = 1;

            foreach (StageType::cases() as $stage) {
                TemplateSection::create([
                    'template_id' => $template->id,
                    'stage' => $stage,
                    'heading' => $headings[$stage->value],
                    'sort_order' => $sortOrder,
                ]);

                $sortOrder++;
            }
        });
    }
}
