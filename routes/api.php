<?php

use App\Http\Controllers\Api\Admin\AdminDashboardController;
use App\Http\Controllers\Api\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Api\Admin\TutorVerificationController;
use App\Http\Controllers\Api\Admin\UserManagementController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\AboutController;
use App\Http\Controllers\Api\AiChatController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardSiswaController;
use App\Http\Controllers\Api\DashboardTutorController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\ForumCategoryController;
use App\Http\Controllers\Api\ForumCommentController;
use App\Http\Controllers\Api\ForumPostController;
use App\Http\Controllers\Api\LiveSessionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PasswordLoginController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PlatformController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SessionNoteController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\SubjectController;
use App\Http\Controllers\Api\TutorAvailabilityController;
use App\Http\Controllers\Api\TutorController;
use App\Http\Controllers\Api\TutorMaterialController;
use App\Http\Controllers\Api\TutorRegistrationController;
use App\Http\Controllers\Api\WebsiteRatingController;
use App\Http\Controllers\Api\WithdrawalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — TUTORKU
|--------------------------------------------------------------------------
*/

// ───────── AUTHENTICATION ─────────
// Google OAuth
Route::get('/auth/google/redirect', [AuthController::class, 'googleRedirectUrl']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/auth/google/verify-otp', [AuthController::class, 'verifyGoogleOtp']);
Route::post('/auth/google/resend-otp', [AuthController::class, 'resendGoogleOtp']);

// Phone OTP
Route::post('/auth/phone/send-otp', [AuthController::class, 'sendPhoneOtp']);
Route::post('/auth/phone/verify-otp', [AuthController::class, 'verifyPhoneOtp']);

// Login with Phone (tanpa OTP)
Route::post('/auth/login-phone', [AuthController::class, 'loginWithPhone']);

// Register with Phone
Route::post('/auth/register', [AuthController::class, 'registerWithPhone']);

// Tutor Login
Route::post('/auth/tutor/login', [PasswordLoginController::class, 'tutorLogin']);
Route::get('/auth/tutor/google/redirect', [PasswordLoginController::class, 'tutorGoogleRedirectUrl']);
Route::get('/auth/tutor/google/callback', [PasswordLoginController::class, 'tutorGoogleCallback']);

// Admin Login
Route::post('/auth/admin/login', [PasswordLoginController::class, 'adminLogin']);

// ───────── PUBLIC ROUTES ─────────
Route::get('/platform/stats', [PlatformController::class, 'stats']);
Route::get('/website/ratings', [WebsiteRatingController::class, 'index']);
Route::get('/subjects', [SubjectController::class, 'index']);
Route::get('/tutors', [TutorController::class, 'index']);
Route::get('/tutors/{tutorProfile}', [TutorController::class, 'show']);
Route::get('/tutors/{tutorProfile}/available-slots', [TutorController::class, 'availableSlots']);
Route::get('/tutors/{tutorProfileId}/reviews', [ReviewController::class, 'index']);
Route::get('/materials', [TutorMaterialController::class, 'publicIndex']);
Route::get('/materials/{material}', [TutorMaterialController::class, 'show']);
Route::post('/materials/{material}/views', [TutorMaterialController::class, 'incrementView']);
Route::get('/forum/categories', [ForumCategoryController::class, 'index']);
Route::get('/forum/posts', [ForumPostController::class, 'index']);
Route::get('/forum/posts/{forumPost}', [ForumPostController::class, 'show']);
Route::get('/about', [AboutController::class, 'index']);
Route::post('/contact', [ContactController::class, 'store']);

// Payment Webhooks
Route::post('/payments/webhook/midtrans', [PaymentController::class, 'midtransWebhook']);
Route::post('/payments/webhook/xendit', [PaymentController::class, 'xenditWebhook']);

// AI Chat (Groq - Free & Unlimited)
Route::post('/ai/chat', [AiChatController::class, 'chat']);

// ───────── AUTHENTICATED ROUTES ─────────
Route::middleware('auth:sanctum')->group(function () {

    // Broadcasting Auth
    Route::post('/broadcasting/auth', function (\Illuminate\Http\Request $request) {
        return \Illuminate\Support\Facades\Broadcast::auth($request);
    });

    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::get('/auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{id}', [AuthController::class, 'revokeSession']);

    // Profile & Settings
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
    Route::get('/settings', [SettingsController::class, 'show']);
    Route::put('/settings', [SettingsController::class, 'update']);

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/{notificationId}/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::delete('/notifications/{notificationId}', [NotificationController::class, 'destroy']);

    // Website Ratings
    Route::post('/website/ratings', [WebsiteRatingController::class, 'store']);

    // FCM Tokens (Push Notifications)
    Route::post('/fcm-tokens', [FcmTokenController::class, 'register']);
    Route::get('/fcm-tokens', [FcmTokenController::class, 'list']);
    Route::delete('/fcm-tokens/{token}', [FcmTokenController::class, 'unregister']);
    Route::delete('/fcm-tokens', [FcmTokenController::class, 'deleteAll']);

    // Bookings
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/bulk-destroy', [BookingController::class, 'bulkDestroy']);
    Route::get('/bookings/{booking}', [BookingController::class, 'show']);
    Route::post('/bookings/{booking}/confirm', [BookingController::class, 'confirm']);
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);
    Route::post('/bookings/{booking}/complete', [BookingController::class, 'complete']);
    Route::post('/bookings/{booking}/review', [ReviewController::class, 'store']);

    // Payments
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::post('/payments/{payment}/simulate', [PaymentController::class, 'simulate']);
    Route::post('/payments/{payment}/check-status', [PaymentController::class, 'checkStatus']);

    // Live Session
    Route::get('/bookings/{booking}/live-session', [LiveSessionController::class, 'show']);
    Route::post('/bookings/{booking}/live-session/join', [LiveSessionController::class, 'join']);
    Route::post('/bookings/{booking}/live-session/pause', [LiveSessionController::class, 'pause']);
    Route::post('/bookings/{booking}/live-session/resume', [LiveSessionController::class, 'resume']);
    Route::post('/bookings/{booking}/live-session/end', [LiveSessionController::class, 'end']);
    Route::post('/bookings/{booking}/live-session/signal', [LiveSessionController::class, 'signal']);
    Route::post('/bookings/{booking}/live-session/whiteboard', [LiveSessionController::class, 'whiteboard']);
    Route::post('/bookings/{booking}/session-note', [SessionNoteController::class, 'store']);
    Route::get('/bookings/{booking}/session-note', [SessionNoteController::class, 'show']);

    // Chat
    Route::get('/chat/conversations', [ChatController::class, 'index']);
    Route::post('/chat/conversations/start', [ChatController::class, 'start']);
    Route::get('/chat/conversations/{conversation}/messages', [ChatController::class, 'messages']);
    Route::post('/chat/conversations/{conversation}/messages', [ChatController::class, 'send']);
    Route::post('/chat/conversations/{conversation}/read', [ChatController::class, 'markRead']);
    Route::post('/chat/conversations/{conversation}/typing', [ChatController::class, 'typing']);
    Route::patch('/chat/messages/{message}', [ChatController::class, 'updateMessage']);
    Route::delete('/chat/messages/{message}', [ChatController::class, 'deleteMessage']);

    // Forum
    Route::post('/forum/posts', [ForumPostController::class, 'store']);
    Route::put('/forum/posts/{forumPost}', [ForumPostController::class, 'update']);
    Route::delete('/forum/posts/{forumPost}', [ForumPostController::class, 'destroy']);
    Route::post('/forum/posts/{forumPost}/like', [ForumPostController::class, 'toggleLike']);
    Route::post('/forum/posts/{forumPost}/bookmark', [ForumPostController::class, 'toggleBookmark']);
    Route::post('/forum/posts/{forumPost}/solved', [ForumPostController::class, 'markSolved']);
    Route::post('/forum/posts/{forumPost}/comments', [ForumCommentController::class, 'store']);
    Route::post('/forum/comments/{forumComment}/like', [ForumCommentController::class, 'toggleLike']);
    Route::post('/forum/comments/{forumComment}/mark-solution', [ForumCommentController::class, 'markSolution']);
    Route::delete('/forum/comments/{forumComment}', [ForumCommentController::class, 'destroy']);

    // Reports
    Route::post('/reports', [ReportController::class, 'store']);

    // Tutor Like/Dislike
    Route::post('/tutors/{tutorProfile}/like', [TutorController::class, 'like']);
    Route::post('/tutors/{tutorProfile}/dislike', [TutorController::class, 'dislike']);

    // Favorites
    Route::get('/favorites', [TutorController::class, 'favorites']);
    Route::post('/tutors/{tutorProfile}/favorite', [TutorController::class, 'toggleFavorite']);

    Route::post('/materials/{material}/like', [TutorMaterialController::class, 'like']);
    Route::post('/materials/{material}/dislike', [TutorMaterialController::class, 'dislike']);
    Route::post('/materials/{material}/comments', [TutorMaterialController::class, 'comment']);

    // Progress
    Route::get('/progress', [ProgressController::class, 'index']);

    // ───────── SISWA ROUTES ─────────
    Route::middleware('role:siswa')->group(function () {
        Route::get('/dashboard/siswa', [DashboardSiswaController::class, 'overview']);
    });

    // ───────── TUTOR REGISTRATION ─────────
    Route::middleware('role:siswa')->prefix('tutor')->group(function () {
        Route::post('/registration/start', [TutorRegistrationController::class, 'start']);
        Route::get('/registration', [TutorRegistrationController::class, 'show']);
        Route::put('/registration/step-2', [TutorRegistrationController::class, 'step2']);
        Route::put('/registration/step-3', [TutorRegistrationController::class, 'step3']);
        Route::post('/registration/step-4', [TutorRegistrationController::class, 'step4']);
        Route::post('/registration/submit', [TutorRegistrationController::class, 'submit']);
    });

    // ───────── TUTOR ROUTES ─────────
    Route::middleware('role:tutor')->group(function () {
        Route::get('/dashboard/tutor', [DashboardTutorController::class, 'overview']);
        Route::get('/dashboard/tutor/students', [DashboardTutorController::class, 'myStudents']);
        Route::get('/tutor/reviews', [ReviewController::class, 'myReviews']);

        Route::apiResource('/tutor/availabilities', TutorAvailabilityController::class)
            ->only(['index', 'store', 'update', 'destroy'])
            ->parameters(['availabilities' => 'availability']);

        Route::get('/tutor/materials', [TutorMaterialController::class, 'index']);
        Route::post('/tutor/materials', [TutorMaterialController::class, 'store']);
        Route::put('/tutor/materials/{material}', [TutorMaterialController::class, 'update']);
        Route::delete('/tutor/materials/{material}', [TutorMaterialController::class, 'destroy']);

        Route::get('/tutor/withdrawals', [WithdrawalController::class, 'index']);
        Route::post('/tutor/withdrawals', [WithdrawalController::class, 'store']);
    });

    // ───────── ADMIN ROUTES ─────────
    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'overview']);

        Route::get('/tutors', [TutorVerificationController::class, 'index']);
        Route::get('/tutors/{tutorProfile}', [TutorVerificationController::class, 'show']);
        Route::post('/tutors/{tutorProfile}/approve', [TutorVerificationController::class, 'approve']);
        Route::post('/tutors/{tutorProfile}/reject', [TutorVerificationController::class, 'reject']);

        Route::get('/users', [UserManagementController::class, 'index']);
        Route::post('/users', [UserManagementController::class, 'store']);
        Route::get('/users/{user}', [UserManagementController::class, 'show']);
        Route::put('/users/{user}/status', [UserManagementController::class, 'updateStatus']);

        Route::get('/reports', [AdminReportController::class, 'index']);
        Route::put('/reports/{report}/resolve', [AdminReportController::class, 'resolve']);

        Route::get('/bookings', [AdminBookingController::class, 'index']);
        Route::get('/bookings/{booking}', [AdminBookingController::class, 'show']);
        Route::post('/bookings/{booking}/cancel', [AdminBookingController::class, 'cancel']);

        Route::get('/payments', [AdminPaymentController::class, 'index']);
        Route::get('/payments/{payment}', [AdminPaymentController::class, 'show']);

        Route::get('/withdrawals', [AdminWithdrawalController::class, 'index']);
        Route::put('/withdrawals/{withdrawal}/status', [AdminWithdrawalController::class, 'updateStatus']);
        // masuk
    });
});