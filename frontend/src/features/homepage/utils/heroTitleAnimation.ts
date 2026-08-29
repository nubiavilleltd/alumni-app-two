const HERO_TITLE_ROTATE_START = "[HERO_TITLE_ROTATE]";
const HERO_TITLE_ROTATE_END = "[/HERO_TITLE_ROTATE]";
const HERO_TITLE_ROTATE_PATTERN =
  /\s*\[HERO_TITLE_ROTATE\]\s*([\s\S]*?)\s*\[\/HERO_TITLE_ROTATE\]\s*$/;
const DEFAULT_TITLE = "welcome home";
const DEFAULT_ALTERNATE_WORD = "Everyone";

type StoredHeroTitleAnimation = {
  alternateLastWord?: unknown;
};

export type HeroTitleAnimation = {
  displayTitle: string;
  alternateLastWord: string;
  animatedWords: string[];
  shouldAnimate: boolean;
  hasStoredAlternate: boolean;
};

function normalizeSingleWord(value: string) {
  return value.trim().split(/\s+/)[0] ?? "";
}

function getLastWord(value: string) {
  return value.trim().match(/(\S+)$/)?.[1] ?? "";
}

function parseStoredAlternate(value: string) {
  try {
    const parsed = JSON.parse(value) as StoredHeroTitleAnimation;
    return typeof parsed.alternateLastWord === "string"
      ? parsed.alternateLastWord
      : "";
  } catch {
    return value;
  }
}

export function parseHeroTitleAnimation(title: string): HeroTitleAnimation {
  const rawTitle = String(title ?? "");
  const storedMatch = rawTitle.match(HERO_TITLE_ROTATE_PATTERN);
  const displayTitle = (
    storedMatch ? rawTitle.replace(HERO_TITLE_ROTATE_PATTERN, "") : rawTitle
  ).trim();
  const storedAlternateLastWord = storedMatch
    ? normalizeSingleWord(parseStoredAlternate(storedMatch[1]))
    : "";
  const defaultAlternateLastWord =
    !storedAlternateLastWord && displayTitle.toLowerCase() === DEFAULT_TITLE
      ? DEFAULT_ALTERNATE_WORD
      : "";
  const alternateLastWord = storedAlternateLastWord || defaultAlternateLastWord;
  const titleLastWord = getLastWord(displayTitle);
  const shouldAnimate = Boolean(
    titleLastWord &&
    alternateLastWord &&
    alternateLastWord.toLowerCase() !== titleLastWord.toLowerCase(),
  );

  return {
    displayTitle,
    alternateLastWord,
    animatedWords: shouldAnimate ? [titleLastWord, alternateLastWord] : [],
    shouldAnimate,
    hasStoredAlternate: Boolean(storedAlternateLastWord),
  };
}

export function buildHeroTitleWithAnimation(
  displayTitle: string,
  alternateLastWord: string,
) {
  const cleanTitle = displayTitle.trim();
  const cleanAlternateLastWord = normalizeSingleWord(alternateLastWord);

  if (!cleanAlternateLastWord) {
    return cleanTitle;
  }

  return `${cleanTitle}\n${HERO_TITLE_ROTATE_START}${JSON.stringify({
    alternateLastWord: cleanAlternateLastWord,
  })}${HERO_TITLE_ROTATE_END}`;
}
