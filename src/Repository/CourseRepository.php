<?php

namespace App\Repository;

use App\Entity\Course;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Course>
 */
class CourseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Course::class);
    }

    public function findAllWithBilling(array $billingInfo): array
    {
        // Собираем коды из billingInfo для фильтрации
        $billingCodes = array_column($billingInfo, 'code');
        
        // Создаем ассоциативный массив для быстрого доступа к данным биллинга
        $billingMap = array_reduce($billingInfo, function ($carry, $item) {
            $carry[$item['code']] = [
                'type' => $item['type'],
                'price' => $item['type'] === 'free' ? 0.00 : (float)$item['price']
            ];
            return $carry;
        }, []);

        // Основной запрос с джойном уроков
        $qb = $this->createQueryBuilder('c');
        $qb->select('c', 'l')
        ->leftJoin('c.lessons', 'l')
        ->addOrderBy('l.orderNumber', 'ASC');

        // Если есть коды для фильтрации
        if (!empty($billingCodes)) {
            $qb->andWhere('c.code IN (:codes)')
            ->setParameter('codes', $billingCodes);
        }

        $courses = $qb->getQuery()->getResult();

        // Формируем результат
        return array_map(function (Course $course) use ($billingMap) {
            $courseData = [
                'id' => $course->getId(),
                'title' => $course->getTitle(),
                'code' => $course->getCode(),
                'description' => $course->getDescription(),
                'lessons' => $course->getLessons()->toArray(),
                'type' => 'free', // значение по умолчанию
                'price' => 0.00    // значение по умолчанию
            ];

            // Если есть данные биллинга для этого курса
            if (isset($billingMap[$course->getCode()])) {
                $billingData = $billingMap[$course->getCode()];
                $courseData['type'] = $billingData['type'];
                $courseData['price'] = $billingData['price'];
            }

            return $courseData;
        }, $courses);
    }

    //    /**
    //     * @return Course[] Returns an array of Course objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Course
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
