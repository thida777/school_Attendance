package com.school.attendance.apis;

import com.school.attendance.models.RosterResponse;
import com.school.attendance.models.SubmitRequest;
import com.school.attendance.models.SubmitResponse;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Query;

/** Mark attendance. Full list of addresses: Student_Attendance_Analytics/docs/API.md */
public interface AttendanceAPI {

    /** GET /api/v1/attendance/roster?slot_id=… → the students of one of today's lessons. */
    @GET("attendance/roster")
    Call<RosterResponse> roster(@Query("slot_id") int slotId);

    /** POST /api/v1/attendance → 201 first time, 200 when the lesson was changed. */
    @POST("attendance")
    Call<SubmitResponse> submit(@Body SubmitRequest body);
}
