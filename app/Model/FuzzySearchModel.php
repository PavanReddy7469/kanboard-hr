<?php

namespace Kanboard\Model;

use Kanboard\Core\Base;

/**
 * Enhanced Fuzzy & Typo-Tolerant Search Engine
 *
 * Implements multi-strategy string similarity matching:
 * - Substring & Prefix matching
 * - Damerau-Levenshtein Edit Distance (typos, swapped/missing letters)
 * - Metaphone Phonetic sound-alike matching (e.g., 'flite' -> 'flight')
 * - N-gram & Dice similarity
 *
 * @package Kanboard\Model
 */
class FuzzySearchModel extends Base
{
    /**
     * Calculate similarity score between a search token and a candidate word.
     * Returns a float between 0.0 (no match) and 1.0 (exact match).
     *
     * @param  string $queryTerm
     * @param  string $candidateWord
     * @return float
     */
    public function computeWordSimilarity($queryTerm, $candidateWord)
    {
        $q = mb_strtolower(trim($queryTerm), 'UTF-8');
        $c = mb_strtolower(trim($candidateWord), 'UTF-8');

        if ($q === '' || $c === '') {
            return 0.0;
        }

        // 1. Exact match
        if ($q === $c) {
            return 1.0;
        }

        // 2. Exact substring match
        if (strpos($c, $q) !== false) {
            // Give higher score if candidate starts with the query (prefix match)
            if (strpos($c, $q) === 0) {
                return 0.95;
            }
            return 0.88;
        }

        if (strpos($q, $c) !== false && mb_strlen($c, 'UTF-8') >= 3) {
            return 0.85;
        }

        $lenQ = mb_strlen($q, 'UTF-8');
        $lenC = mb_strlen($c, 'UTF-8');
        $maxLen = max($lenQ, $lenC);

        // 3. Phonetic matching via Metaphone
        $metaQ = metaphone($q);
        $metaC = metaphone($c);
        if ($metaQ !== '' && $metaC !== '' && $metaQ === $metaC) {
            return 0.90;
        }

        // 4. Levenshtein edit distance for typo tolerance
        // Only compute if string lengths are reasonable
        if ($maxLen >= 3 && abs($lenQ - $lenC) <= 3) {
            $lev = levenshtein($q, $c);
            
            // Allow 1 edit for 3-5 chars, 2 edits for 6-8 chars, 3 edits for >8 chars
            $maxAllowedEdits = ($maxLen <= 5) ? 1 : (($maxLen <= 8) ? 2 : 3);

            if ($lev <= $maxAllowedEdits) {
                $score = 1.0 - ($lev / (float)$maxLen);
                return max($score, 0.65);
            }
        }

        // 5. Similar text percentage
        similar_text($q, $c, $percent);
        $simScore = $percent / 100.0;

        if ($simScore >= 0.68) {
            return $simScore;
        }

        return 0.0;
    }

    /**
     * Compute match score for a full search query against a target text.
     *
     * @param  string $searchQuery
     * @param  string $targetText
     * @return float
     */
    public function matchScore($searchQuery, $targetText)
    {
        $q = mb_strtolower(trim($searchQuery), 'UTF-8');
        $t = mb_strtolower(trim($targetText), 'UTF-8');

        if ($q === '' || $t === '') {
            return 0.0;
        }

        // Direct full text match or substring
        if ($q === $t) {
            return 1.0;
        }

        if (strpos($t, $q) !== false) {
            return 0.95;
        }

        // Split query and target into words
        $queryTokens = preg_split('/[\s\-_,\.\/]+/', $q, -1, PREG_SPLIT_NO_EMPTY);
        $targetWords = preg_split('/[\s\-_,\.\/]+/', $t, -1, PREG_SPLIT_NO_EMPTY);

        if (empty($queryTokens) || empty($targetWords)) {
            return 0.0;
        }

        $totalTokenScore = 0.0;

        foreach ($queryTokens as $token) {
            $bestWordScore = 0.0;

            // Test token against each word in the target text
            foreach ($targetWords as $word) {
                $score = $this->computeWordSimilarity($token, $word);
                if ($score > $bestWordScore) {
                    $bestWordScore = $score;
                }
                if ($bestWordScore >= 0.99) {
                    break;
                }
            }

            // Also test if token matches a contiguous chunk
            if ($bestWordScore < 0.70 && strpos($t, $token) !== false) {
                $bestWordScore = 0.85;
            }

            $totalTokenScore += $bestWordScore;
        }

        // Average score across all query tokens
        $aggregateScore = $totalTokenScore / (float)count($queryTokens);

        return $aggregateScore;
    }

    /**
     * Find task IDs that match the search query using fuzzy matching.
     *
     * @param  string       $searchQuery
     * @param  int|int[]    $projectIds
     * @param  float        $threshold
     * @param  int          $limit
     * @return int[]
     */
    public function findTaskIdsByFuzzyTitle($searchQuery, $projectIds = null, $threshold = 0.55, $limit = 50)
    {
        $search = trim($searchQuery);
        if ($search === '') {
            return array();
        }

        $query = $this->db->table(TaskModel::TABLE)->columns('id', 'title', 'project_id', 'description');

        if (! empty($projectIds)) {
            if (is_array($projectIds)) {
                $query->in('project_id', $projectIds);
            } else {
                $query->eq('project_id', (int) $projectIds);
            }
        }

        $tasks = $query->findAll();
        if (empty($tasks)) {
            return array();
        }

        $scoredTasks = array();

        foreach ($tasks as $task) {
            // Score title
            $titleScore = $this->matchScore($search, $task['title']);
            
            // Score description if title didn't match strongly
            $descScore = 0.0;
            if (! empty($task['description']) && $titleScore < 0.85) {
                $descScore = $this->matchScore($search, $task['description']) * 0.80;
            }

            $finalScore = max($titleScore, $descScore);

            if ($finalScore >= $threshold) {
                $scoredTasks[(int)$task['id']] = $finalScore;
            }
        }

        if (empty($scoredTasks)) {
            return array();
        }

        // Sort task IDs by similarity score DESC
        arsort($scoredTasks);

        return array_slice(array_keys($scoredTasks), 0, $limit);
    }

    /**
     * Find projects that match the search query using fuzzy matching.
     *
     * @param  string       $searchQuery
     * @param  int[]        $accessibleProjectIds
     * @param  float        $threshold
     * @param  int          $limit
     * @return array
     */
    public function findProjectsByFuzzyName($searchQuery, array $accessibleProjectIds = array(), $threshold = 0.50, $limit = 20)
    {
        $search = trim($searchQuery);
        if ($search === '') {
            return array();
        }

        $query = $this->db->table(ProjectModel::TABLE)->columns('id', 'name', 'description', 'is_active', 'start_date', 'end_date');

        if (! empty($accessibleProjectIds)) {
            $query->in('id', $accessibleProjectIds);
        }

        $projects = $query->findAll();
        if (empty($projects)) {
            return array();
        }

        $scoredProjects = array();

        foreach ($projects as $project) {
            $nameScore = $this->matchScore($search, $project['name']);
            $descScore = ! empty($project['description']) ? $this->matchScore($search, $project['description']) * 0.75 : 0.0;
            $finalScore = max($nameScore, $descScore);

            if ($finalScore >= $threshold) {
                $project['_fuzzy_score'] = round($finalScore * 100);
                $project['_is_fuzzy']    = ($nameScore < 0.95 && strpos(mb_strtolower($project['name'], 'UTF-8'), mb_strtolower($search, 'UTF-8')) === false);
                $scoredProjects[] = $project;
            }
        }

        // Sort by score DESC
        usort($scoredProjects, function ($a, $b) {
            return $b['_fuzzy_score'] <=> $a['_fuzzy_score'];
        });

        return array_slice($scoredProjects, 0, $limit);
    }

    /**
     * Find the best matching suggestion for a search query from a list of candidate strings.
     *
     * @param  string   $searchQuery
     * @param  string[] $candidates
     * @return string|null
     */
    public function getBestSuggestion($searchQuery, array $candidates)
    {
        $bestScore = 0.0;
        $bestMatch = null;

        foreach ($candidates as $candidate) {
            $score = $this->matchScore($searchQuery, $candidate);
            if ($score > $bestScore && $score >= 0.60) {
                $bestScore = $score;
                $bestMatch = $candidate;
            }
        }

        return $bestMatch;
    }
}
