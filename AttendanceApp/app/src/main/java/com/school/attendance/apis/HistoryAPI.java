package com.school.attendance.apis;

import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.CorrectRequest;
import com.school.attendance.models.HistoryResponse;

import java.util.Map;

import retrofit2.Call;
import retrofit2.http.Body;
import retrofit2.http.GET;
import retrofit2.http.POST;
import retrofit2.http.Path;
import retrofit2.http.QueryMap;

/** Attendance history and changes. Full list of addresses: Student_Attendance_Analytics/docs/API.md */
public interface HistoryAPI {

    /** GET /api/v1/history?class_id=&status=absent&q=&page=1 → the teacher's records, newest first. */
    @GET("history")
    Call<HistoryResponse> history(@QueryMap Map<String, String> filters);

    /**
     * POST /api/v1/records/{id}/correct
     * 200 = changed at once (within 24 hours); 202 = sent as a request to the admin (after 24 hours).
     * The answer's "message" says which one, in the app's language.
     */
    @POST("records/{id}/correct")
    Call<ApiMessage> correct(@Path("id") int recordId, @Body CorrectRequest body);
}
