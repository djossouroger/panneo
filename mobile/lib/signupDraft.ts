export type SignupDraft = {
  name: string;
  email: string;
  phone: string;
  password: string;
  passwordConfirmation: string;
  categoryId: number | null;
  city: string;
  district: string;
};

let draft: SignupDraft | null = null;

export function setSignupDraft(nextDraft: SignupDraft): void {
  draft = nextDraft;
}

export function getSignupDraft(): SignupDraft | null {
  return draft;
}

export function clearSignupDraft(): void {
  draft = null;
}
