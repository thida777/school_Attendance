package com.school.attendance.apis;

import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.CallsResponse;
import com.school.attendance.models.ClassListResponse;
import com.school.attendance.models.StudentListResponse;
import com.school.attendance.models.TodayResponse;

import java.util.Map;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Path;

/** What the teacher teaches. Full list of addresses: Student_Attendance_Analytics/docs/API.md */
public interface TeachingAPI {

    /** GET /api/v1/today → today's lessons, with done / not marked. */
    @GET("today")
    Call<TodayResponse> today();

    /** GET /api/v1/classes → the teacher's classes with students and attendance rate. */
    @GET("classes")
    Call<ClassListResponse> classes();

    /** GET /api/v1/classes/{id}/students → students with rate, risk and parent (read only). */
    @GET("classes/{id}/students")
    Call<StudentListResponse> students(@Path("id") int classId);

    /** GET /api/v1/calls → parents who asked for a call (I am the homeroom teacher). */
    @GET("calls")
    Call<CallsResponse> calls();

    /** POST /api/v1/calls/{id}/done {"note": "..."} → I called; the request leaves the list. */
    @POST("calls/{id}/done")
    Call<ApiMessage> callDone(@Path("id") int callId, @Body Map<String, String> body);
}
