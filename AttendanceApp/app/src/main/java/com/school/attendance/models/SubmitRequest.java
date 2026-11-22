package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.ArrayList;
import java.util.List;

/**
 * Body of POST /attendance:
 * {"school_class_id": 4, "subject_id": 2, "date": "2026-11-17", "period": 1,
 *  "records": [{"student_id": 5, "status": "absent", "absence_reason": "sick"}, ...]}
 */
public class SubmitRequest {

    @SerializedName("school_class_id")
    private final int schoolClassId;

    @SerializedName("subject_id")
    private final int subjectId;

    private final String date;
    private final int period;
    private final List<Record> records = new ArrayList<>();

    public SubmitRequest(int schoolClassId, int subjectId, String date, int period) {
        this.schoolClassId = schoolClassId;
        this.subjectId = subjectId;
        this.date = date;
        this.period = period;
    }

    /** Every student is sent, so a student changed back to Present is saved too. */
    public void add(int studentId, String status, String absenceReason) {
        records.add(new Record(studentId, status, absenceReason));
    }

    static class Record {
        @SerializedName("student_id")
        final int studentId;

        final String status;

        @SerializedName("absence_reason")
        final String absenceReason;

        Record(int studentId, String status, String absenceReason) {
            this.studentId = studentId;
            this.status = status;
            this.absenceReason = absenceReason;
        }
    }
}
