package com.school.attendance.apis;

import com.school.attendance.models.ClassListResponse;
import com.school.attendance.models.StudentListResponse;
import com.school.attendance.models.TodayResponse;

import retrofit2.Call;
import retrofit2.http.GET;
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
}
