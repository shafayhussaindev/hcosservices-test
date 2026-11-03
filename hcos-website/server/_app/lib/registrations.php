<?php
declare(strict_types=1);

const HCOS_REG_GENDERS = [
  'male' => 'Male', 'female' => 'Female', 'other' => 'Other',
];

const HCOS_REG_PRICES = [
  'corporate' => 'Corporate Sector Rs:3000 per participant',
  'student' => 'For Students Rs:1500 per Participant',
];

function registration_gender_label(?string $gender): string {
  return match ($gender) {
    'man' => 'Male', 'woman' => 'Female', 'non_binary' => 'Other',
    default => HCOS_REG_GENDERS[$gender ?? ''] ?? 'Not supplied',
  };
}

function valid_linkedin_profile(string $url): bool {
  if (strlen($url) > 512 || !filter_var($url, FILTER_VALIDATE_URL)) return false;
  $parts = parse_url($url);
  $host = strtolower($parts['host'] ?? '');
  return ($parts['scheme'] ?? '') === 'https'
    && ($host === 'linkedin.com' || str_ends_with($host, '.linkedin.com'))
    && !isset($parts['user']) && !isset($parts['pass']) && !isset($parts['port'])
    && (bool)preg_match('#^/(in|pub)/[^/\s]+/?$#', $parts['path'] ?? '');
}
