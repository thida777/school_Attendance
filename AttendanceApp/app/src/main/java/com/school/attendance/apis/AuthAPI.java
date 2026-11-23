package com.school.attendance.apis;

import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.ForgotRequest;
import com.school.attendance.models.ResetRequest;
import com.school.attendance.models.LocaleRequest;
import com.school.attendance.models.LoginRequest;
import com.school.attendance.models.LoginResponse;
import com.school.attendance.models.PasswordRequest;
import com.school.attendance.models.UserResponse;

import okhttp3.MultipartBody;
import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.DELETE;
import retrofit2.http.GET;
import retrofit2.http.Multipart;
import retrofit2.http.POST;
import retrofit2.http.PUT;
import retrofit2.http.Part;

/** Login and the logged-in teacher. Full list of addresses: Student_Attendance_Analytics/docs/API.md */
public interface AuthAPI {

    /** POST /api/v1/login → token + teacher. Admin accounts get 403 (the app is for teachers). */
    @POST("login")
    Call<LoginResponse> login(@Body LoginRequest body);

    /** POST /api/v1/password/forgot → the school bot sends a 6-digit code (same answer for every account). */
    @POST("password/forgot")
    Call<ApiMessage> forgotPassword(@Body ForgotRequest body);

    /** POST /api/v1/password/reset → new password; all old logins are signed out. */
    @POST("password/reset")
    Call<ApiMessage> resetPassword(@Body ResetRequest body);

    /** POST /api/v1/logout → logs out this phone only. */
    @POST("logout")
    Call<ApiMessage> logout();

    /** GET /api/v1/me → the teacher again (checks that the saved token still works). */
    @GET("me")
    Call<UserResponse> me();

    /** PUT /api/v1/me → saves the app language on the server (messages, website). */
    @PUT("me")
    Call<UserResponse> updateLocale(@Body LocaleRequest body);

    /** PUT /api/v1/me/password → other phones are logged out; this one stays. */
    @PUT("me/password")
    Call<ApiMessage> changePassword(@Body PasswordRequest body);

    /** POST /api/v1/me/photo (multipart, field "photo") → the teacher with the new photo_url. */
    @Multipart
    @POST("me/photo")
    Call<UserResponse> uploadPhoto(@Part MultipartBody.Part photo);

    /** DELETE /api/v1/me/photo → back to the initials. */
    @DELETE("me/photo")
    Call<UserResponse> removePhoto();
}
