<?php

declare(strict_types=1);

namespace App\Service\Position;

use App\Entity\Cv;
use App\Entity\Position;
use App\Entity\User;
use App\Enum\CvStatus;
use App\Repository\CvRepository;
use App\Repository\PositionRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

class PositionViewService
{
    public function __construct(
        private readonly PositionRepository $positionRepository,
        private readonly CvRepository $cvRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Получает позиции для списка (с учетом роли пользователя)
     */
    public function getPositionsForIndex(?User $user, int $page, int $limit = 20): array
    {
        if ($user !== null && $this->isCandidate($user)) {
            $profile = $user->getProfile();
            if ($profile === null) {
                return ['items' => [], 'total' => 0, 'accessibleById' => []];
            }

            return $this->positionRepository->findPositionsForCandidate(
                $profile->getId(),
                true,
                $page,
                $limit
            );
        }

        // Guests and recruiters see everything
        $positions = $this->positionRepository->findLatest($limit);

        return [
            'items' => $positions,
            'total' => count($positions),
            'accessibleById' => [],
        ];
    }

    /**
     * Получает ID опубликованных CV по ID авторов
     *
     * @param list<int> $authorIds
     * @return array<int, int> user id => cv id
     */
    public function getPublishedCvIdByAuthorIds(array $authorIds): array
    {
        if ($authorIds === []) {
            return [];
        }

        $rows = $this->entityManager->createQueryBuilder()
            ->select('u.id AS uid', 'c.id AS cid')
            ->from(Cv::class, 'c')
            ->join('c.candidate', 'u')
            ->where('u.id IN (:ids)')
            ->andWhere('c.status = :published')
            ->andWhere('c.isVisible = true')
            ->setParameter('ids', $authorIds, ArrayParameterType::INTEGER)
            ->setParameter('published', CvStatus::PUBLISHED)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($rows as $row) {
            $uid = (int) $row['uid'];
            if (!isset($map[$uid])) {
                $map[$uid] = (int) $row['cid'];
            }
        }

        return $map;
    }

    /**
     * Проверяет, подавал ли кандидат CV на позицию
     */
    public function hasAppliedCv(User $user, Position $position): bool
    {
        return $this->cvRepository->findOneBy([
            'candidate' => $user,
            'position' => $position,
        ]) !== null;
    }

    private function isCandidate(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // В реальном проекте используйте voter или роли
        return in_array('ROLE_CANDIDATE', $user->getRoles(), true)
            && !in_array('ROLE_RECRUITER', $user->getRoles(), true);
    }
}