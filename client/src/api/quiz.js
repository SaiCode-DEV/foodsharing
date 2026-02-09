import { get, patch, post, remove } from './base'

const testParam = (isTest) => isTest ? '?isTest=1' : ''

export function getQuizStatus (quizId, isTest = false) {
  return get(`/users/current/quiz-sessions/${quizId}/status${testParam(isTest)}`)
}

export function getQuizResults (quizId, isTest = false) {
  return get(`/users/current/quiz-sessions/${quizId}/results${testParam(isTest)}`)
}

export function getQuestion (quizId, isTest = false) {
  return get(`/users/current/quiz-sessions/${quizId}/question${testParam(isTest)}`)
}

export function startQuiz (quizId, isTimed = true, isTest = false) {
  return post(`/users/current/quiz-sessions/${quizId}?isTimed=${+isTimed}&isTest=${+isTest}`)
}

export function answerQuestion (quizId, selectedAnswers, isTest = false) {
  return post(`/users/current/quiz-sessions/${quizId}/answer${testParam(isTest)}`, { ids: selectedAnswers })
}

export function confirmQuiz (quizId) {
  return post(`/users/current/quiz-sessions/${quizId}/confirmation`)
}

export function getQuizSessionHistory (userId) {
  return get(`/users/${userId}/quiz-sessions`)
}

export function deleteQuizSession (sessionId) {
  return remove(`/quiz-sessions/${sessionId}`)
}

export function getQuiz (quizId) {
  return get(`/quizzes/${quizId}`)
}

export function getQuestions (quizId) {
  return get(`/quizzes/${quizId}/questions`)
}

export function editQuiz (quizId, data) {
  return patch(`/quizzes/${quizId}`, data)
}

export function editQuestion (quizId, questionId, data) {
  return patch(`/quizzes/${quizId}/questions/${questionId}`, data)
}

export function editAnswer (quizId, questionId, answerId, data) {
  return patch(`/quizzes/${quizId}/questions/${questionId}/answers/${answerId}`, data)
}

export function deleteQuestion (quizId, questionId) {
  return remove(`/quizzes/${quizId}/questions/${questionId}`)
}

export function deleteAnswer (quizId, questionId, answerId) {
  return remove(`/quizzes/${quizId}/questions/${questionId}/answers/${answerId}`)
}

export async function addAnswer (quizId, questionId, data) {
  return (await post(`/quizzes/${quizId}/questions/${questionId}/answers`, data)).id
}

export async function addQuestion (quizId, data) {
  return (await post(`/quizzes/${quizId}/questions`, data)).id
}
