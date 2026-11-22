package com.school.attendance.models;

import java.util.ArrayList;
import java.util.List;

/** GET /classes/{id}/students: {"data": [...]}. */
public class StudentListResponse {

    private List<Student> data;

    public List<Student> getData() {
        return data == null ? new ArrayList<>() : data;
    }
}
