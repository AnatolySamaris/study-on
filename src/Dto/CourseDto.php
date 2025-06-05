<?php

namespace App\Dto;

use App\Entity\Course;
use App\Enum\CourseType;
use Symfony\Component\Validator\Constraints as Assert;

class CourseDto
{
    #[Assert\Choice(choices: [CourseType::FREE, CourseType::PAY, CourseType::RENT])]
    public int $type;

    #[Assert\NotBlank(message: "Title is mandatory")]
    #[Assert\Length(
        min: 255,
        minMessage: "Max course title length is {{ limit }} symbols"
    )]
    public string $title;

    #[Assert\NotBlank(message: "Code is mandatory")]
    #[Assert\Length(
        max: 255,
        maxMessage: "Max course code length is {{ limit }} symbols"
    )]
    public string $code;

    #[Assert\GreaterThan(0)]
    public float|null $price = null;

    public function fromEntity(Course $course): static
    {
        $this->type = $course->getType()->getValue();
        $this->title = $course->getTitle();
        $this->code = $course->getCode();
        $this->price = $course->getPrice();

        return $this;
    }

    public function asArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'code' => $this->code,
            'price' => $this->price
        ];
    }
}
