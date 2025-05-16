<?php

namespace App\Controller;

use App\Entity\Course;
use App\Form\CourseType;
use App\Repository\CourseRepository;
use App\Service\BillingClient;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courses')]
final class CourseController extends AbstractController
{
    public function __construct(
        private BillingClient $billingClient
    ) {
    }

    #[Route(name: 'app_course_index', methods: ['GET'])]
    public function index(CourseRepository $courseRepository): Response
    {
        $billingCourses = $this->billingClient->coursesList();

        $mergedCoursesInfo = $courseRepository->findAllWithBilling($billingCourses);

        return $this->render('course/index.html.twig', [
            'courses' => $mergedCoursesInfo
        ]);
    }

    #[Route('/new', name: 'app_course_new', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $course = new Course();
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($course);
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/new.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/pay', name: 'app_course_pay', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_USER")]
    public function payCourse(Course $course, Request $request): Response
    {
        $user = $this->getUser();

        if ($user) {
            $success = $this->billingClient
                ->payCourse(
                    $user->getApiToken(),
                    $course->getCode()
                )
            ;
            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        } else {
            return $this->redirectToRoute('app_login', [], Response::HTTP_SEE_OTHER);
        }
    }

    #[Route('/{id}/edit', name: 'app_course_edit', methods: ['GET', 'POST'])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    public function edit(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(CourseType::class, $course);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('course/edit.html.twig', [
            'course' => $course,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_course_show', methods: ['GET'])]
    public function show(Course $course): Response
    {
        $billingCourse = $this->billingClient->courseInfoByCode($course->getCode());

        $user = $this->getUser();

        if ($user) {
            $isCourseAvailable = $this->billingClient
                ->isCourseAvailable(
                    $user->getApiToken(),
                    $course->getCode()
                )
            ;
        } else {
            $isCourseAvailable = false;
        }

        if ($isCourseAvailable && $billingCourse['type'] == 'rent') {
            $expires_at = $isCourseAvailable;   // Для rent возвращается дата
        } else {
            $expires_at = null;
        }

        $lessons = $course->getLessons();
        return $this->render('course/show.html.twig', [
            'course' => $course,
            'lessons' => $lessons,
            'course_type' => $billingCourse['type'],
            'course_price' => $billingCourse['price'] ?? 0.00,
            'is_course_available' => $isCourseAvailable,
            'expires_at' => $expires_at
        ]);
    }

    #[Route('/{id}', name: 'app_course_delete', methods: ['POST'])]
    #[IsGranted("ROLE_SUPER_ADMIN")]
    public function delete(Request $request, Course $course, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete' . $course->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($course);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_course_index', [], Response::HTTP_SEE_OTHER);
    }
}
