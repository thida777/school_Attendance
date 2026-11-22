package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** Body of POST /records/{id}/correct: {"new_status": "present", "reason": "Arrived after roll call"}. */
public class CorrectRequest {

    @SerializedName("new_status")
    private final String newStatus;

    private final String reason;

    public CorrectRequest(String newStatus, String reason) {
        this.newStatus = newStatus;
        this.reason = reason;
    }
}
