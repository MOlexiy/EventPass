/** "Continue with Google" is hidden when the deployment has no Google OAuth client (VITE_GOOGLE_SIGN_IN=false). */
export const googleSignInEnabled = import.meta.env.VITE_GOOGLE_SIGN_IN !== 'false'
